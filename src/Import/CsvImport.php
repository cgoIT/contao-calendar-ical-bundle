<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use Cgoit\ContaoCalendarIcalBundle\Import\Csv\CsvParser;
use Cgoit\ContaoCalendarIcalBundle\Widgets\WidgetTrait;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Slug\Slug;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsvImport extends AbstractImport
{
    use WidgetTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Connection $db,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $csrfTokenName,
        private readonly string $projectDir,
        Slug $slug,
    ) {
        parent::__construct($slug);
    }

    public function importFromCSVFile(bool $prepare = true): string
    {
        static::loadDataContainer('tl_calendar_events');

        $fieldnames = [];

        $schemaManager = $this->db->createSchemaManager();
        $dbfields = $schemaManager->listTableColumns('tl_calendar_events');

        foreach ($dbfields as $dbfield) {
            $fieldnames[] = $dbfield->getName();
        }

        $calfields =
            [
                ['title', $GLOBALS['TL_LANG']['tl_calendar_events']['title'][0]],
                ['startTime', $GLOBALS['TL_LANG']['tl_calendar_events']['startTime'][0]],
                ['endTime', $GLOBALS['TL_LANG']['tl_calendar_events']['endTime'][0]],
                ['startDate', $GLOBALS['TL_LANG']['tl_calendar_events']['startDate'][0]],
                ['endDate', $GLOBALS['TL_LANG']['tl_calendar_events']['endDate'][0]],
                ['details', $GLOBALS['TL_LANG']['tl_calendar_events']['details'][0]],
                ['teaser', $GLOBALS['TL_LANG']['tl_calendar_events']['teaser'][0]],
            ];

        if (\in_array('location', $fieldnames, true)) {
            $calfields[] = ['location', $GLOBALS['TL_LANG']['tl_calendar_events']['location'][0]];
        }
        if (\in_array('cep_participants', $fieldnames, true)) {
            $calfields[] = ['cep_participants', $GLOBALS['TL_LANG']['tl_calendar_events']['cep_participants'][0]];
        }
        if (\in_array('location_contact', $fieldnames, true)) {
            $calfields[] = ['location_contact', $GLOBALS['TL_LANG']['tl_calendar_events']['location_contact'][0]];
        }

        $dateFormat = Input::post('dateFormat');
        $timeFormat = Input::post('timeFormat');
        $fields = [];
        $csvvalues = Input::post('csvfield');
        $calvalues = Input::post('calfield');
        $encoding = Input::post('encoding');

        if (!\is_array($csvvalues)) {
            $sessiondata = StringUtil::deserialize(Config::get('calendar_ical.csvimport.data'), true);
            if (\is_array($sessiondata) && 5 === \count($sessiondata)) {
                $csvvalues = $sessiondata[0];
                $calvalues = $sessiondata[1];
                $dateFormat = $sessiondata[2];
                $timeFormat = $sessiondata[3];
                $encoding = $sessiondata[4];
            }
        }

        $importSettings = StringUtil::deserialize(
            $this->requestStack
                ->getCurrentRequest()
                ->getSession()
                ->get('calendar_ical.csvimport.settings'),
            true,
        );

        $data = $this->projectDir.'/'.$importSettings['csv_filename'];
        $parser = new CsvParser($data, !empty((string) $encoding) ? $encoding : 'utf8');
        $header = $parser->extractHeader();

        for ($i = 0; $i < (!empty($header) ? \count($header) : 0); ++$i) {
            $objCSV = $this->getFieldSelector($i, 'csvfield', $header,
                \is_array($csvvalues) ? $csvvalues[$i] : $header[$i]);
            $objCal = $this->getFieldSelector($i, 'calfield', $calfields, $calvalues[$i] ?? null);
            $fields[] = [$objCSV, $objCal];
        }

        if ($prepare) {
            $preview = $parser->getDataArray(5);
            $objTemplate = new BackendTemplate('be_import_calendar_csv_headers');
            $objTemplate->request_token = $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue();

            $objTemplate->lngFields = $GLOBALS['TL_LANG']['tl_calendar_events']['fields'];
            $objTemplate->lngPreview = $GLOBALS['TL_LANG']['tl_calendar_events']['preview'];
            $objTemplate->check = $GLOBALS['TL_LANG']['tl_calendar_events']['check'];
            $objTemplate->header = $header;

            if (!empty($preview)) {
                foreach ($preview as $idx => $line) {
                    if (\is_array($line)) {
                        foreach ($line as $key => $value) {
                            $preview[$idx][$key] = StringUtil::specialchars($value);
                        }
                    }
                }
            }

            $objTemplate->preview = $preview;
            $objTemplate->encoding = $this->getEncodingWidget($encoding);

            if (\function_exists('date_parse_from_format')) {
                $objTemplate->dateFormat = $this->getDateFormatWidget($dateFormat);
                $objTemplate->timeFormat = $this->getTimeFormatWidget($timeFormat);
            }

            $objTemplate->hrefBack = StringUtil::ampersand(str_replace('&key=import', '', (string) Environment::get('request')));
            $objTemplate->goBack = $GLOBALS['TL_LANG']['MSC']['goBack'];
            $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['import_calendar'][0];
            $objTemplate->request = StringUtil::ampersand(Environment::get('request'), true);
            $objTemplate->submit = StringUtil::specialchars($GLOBALS['TL_LANG']['tl_calendar_events']['proceed'][0]);
            $objTemplate->fields = $fields;

            return $objTemplate->parse();
        }
        // save config
        Config::set('calendar_ical.csvimport.data', serialize([
            $csvvalues,
            $calvalues,
            Input::post('dateFormat'),
            Input::post('timeFormat'),
            Input::post('encoding'),
        ]));

        if (!empty($importSettings['csv_deletecalendar']) && !empty($importSettings['csv_pid'])) {
            $objEvents = CalendarEventsModel::findByPid($importSettings['csv_pid']);
            $this->deleteEvents($objEvents);
        }

        $done = false;

        // Get all default values for new entries
        $defaultFields = array_filter($GLOBALS['TL_DCA']['tl_calendar_events']['fields'], static fn ($val) => isset($val['default']));

        while (!$done) {
            $data = $parser->getDataArray();

            if (false !== $data) {
                $eventcontent = [];

                $objEvent = new CalendarEventsModel();
                $objEvent->tstamp = time();
                $objEvent->pid = $importSettings['csv_pid'];
                $objEvent->published = true;

                foreach ($defaultFields as $field => $value) {
                    $varValue = $value['default'];
                    if ($varValue instanceof \Closure) {
                        $varValue = $varValue();
                    }
                    $objEvent->{$field} = $varValue;
                }

                $objEvent->author = BackendUser::getInstance()->id ?? 0;

                foreach ($calvalues as $idx => $value) {
                    if (!empty($value)) {
                        $indexfield = $csvvalues[$idx];
                        $foundindex = array_search($indexfield, $header, true);

                        if (false !== $foundindex && !empty($data[$foundindex])) {
                            switch ($value) {
                                case 'startDate':
                                case 'endDate':
                                    if (\function_exists('date_parse_from_format')) {
                                        $res = date_parse_from_format(Input::post('dateFormat'), $data[$foundindex]);

                                        $objEvent->{$value} = mktime(0, 0, 0, $res['month'], $res['day'], $res['year']);
                                    } else {
                                        $objEvent->{$value} = $this->getTimestampFromDefaultDatetime($data[$foundindex]);
                                    }
                                    break;
                                case 'details':
                                    $eventcontent[] = StringUtil::specialchars($data[$foundindex]);
                                    break;
                                case 'title':
                                    $objEvent->{$value} = StringUtil::specialchars($data[$foundindex]);
                                    $filterEventTitle = $importSettings['csv_filterEventTitle'];
                                    if (!empty($filterEventTitle) && !str_contains(mb_strtolower(StringUtil::specialchars($data[$foundindex])), mb_strtolower((string) $filterEventTitle))) {
                                        continue 3;
                                    }
                                // no break
                                default:
                                    $objEvent->{$value} = StringUtil::specialchars($data[$foundindex]);
                                    break;
                            }
                        }
                    }
                }

                if (empty($objEvent->startDate)) {
                    $today = getdate();
                    $objEvent->startDate = mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']);
                    $objEvent->startTime = $objEvent->startDate;
                }

                if (empty($objEvent->endDate)) {
                    $objEvent->endDate = $objEvent->startDate;
                }

                foreach ($calvalues as $idx => $value) {
                    if (!empty($value)) {
                        $indexfield = $csvvalues[$idx];
                        $foundindex = array_search($indexfield, $header, true);

                        if (false !== $foundindex) {
                            switch ($value) {
                                case 'startTime':
                                    if (\function_exists('date_parse_from_format')) {
                                        $res = date_parse_from_format(Input::post('timeFormat'), $data[$foundindex]);

                                        $objEvent->{$value} = $objEvent->startDate + $res['hour'] * 60 * 60 + $res['minute'] * 60 + $res['second'];
                                    } else {
                                        if (preg_match('/(\\d+):(\\d+)/', (string) $data[$foundindex], $matches)) {
                                            $objEvent->{$value} = $objEvent->startDate + (int) $matches[1] * 60 * 60 + (int) $matches[2] * 60;
                                        }
                                    }
                                    break;
                                case 'endTime':
                                    if (\function_exists('date_parse_from_format')) {
                                        $res = date_parse_from_format(Input::post('timeFormat'), $data[$foundindex]);

                                        $objEvent->{$value} = $objEvent->endDate + $res['hour'] * 60 * 60 + $res['minute'] * 60 + $res['second'];
                                    } else {
                                        if (preg_match('/(\\d+):(\\d+)/', (string) $data[$foundindex], $matches)) {
                                            $objEvent->{$value} = $objEvent->endDate + (int) $matches[1] * 60 * 60 + (int) $matches[2] * 60;
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                }

                if ((!empty($objEvent->startTime) && $objEvent->startDate !== $objEvent->startTime) || (!empty($objEvent->endTime) && $objEvent->endDate !== $objEvent->endTime)) {
                    $objEvent->addTime = true;
                }

                if (empty($objEvent->title)) {
                    $objEvent->title = $GLOBALS['TL_LANG']['tl_calendar_events']['untitled'];
                }

                $timeshift = (int) $importSettings['csv_timeshift'];

                if (0 !== $timeshift) {
                    $objEvent->startDate += $timeshift * 3600;
                    $objEvent->endDate += $timeshift * 3600;
                    if (!empty($objEvent->startTime)) {
                        $objEvent->startTime += $timeshift * 3600;
                    }
                    if (!empty($objEvent->endTime)) {
                        $objEvent->endTime += $timeshift * 3600;
                    }
                }

                $startDate = new Date($importSettings['csv_startdate'], $GLOBALS['TL_CONFIG']['dateFormat']);
                $endDate = new Date($importSettings['csv_enddate'], $GLOBALS['TL_CONFIG']['dateFormat']);

                if (empty($objEvent->source)) {
                    $objEvent->source = 'default';
                }

                if ($objEvent->endDate < $startDate->tstamp || (!empty((string) $importSettings['csv_enddate']) && ($objEvent->startDate > $endDate->tstamp))) {
                    // date is not in range
                } else {
                    $objEvent = $objEvent->save();
                    if (!empty($eventcontent)) {
                        $this->addEventContent($objEvent, $eventcontent);
                    }

                    $this->generateAlias($objEvent);
                }
            } else {
                $done = true;
            }
        }

        $this->requestStack
            ->getCurrentRequest()
            ->getSession()
            ->remove('calendar_ical.csvimport.settings')
        ;

        static::redirect(str_replace('&key=import', '', (string) Environment::get('request')));
    }

    private function getTimestampFromDefaultDatetime(string $strDate): bool|int
    {
        $tstamp = time();

        if (preg_match('/(\\d{4})-(\\d{2})-(\\d{2})\\s+(\\d{2}):(\\d{2}):(\\d{2})/', $strDate, $matches)) {
            $tstamp = mktime((int) $matches[4], (int) $matches[5], (int) $matches[6], (int) $matches[2], (int) $matches[3],
                (int) $matches[1]);
        } elseif (preg_match('/(\\d{4})-(\\d{2})-(\\d{2})\\s+(\\d{2}):(\\d{2})/', $strDate, $matches)) {
            $tstamp = mktime((int) $matches[4], (int) $matches[5], 0, (int) $matches[2], (int) $matches[3],
                (int) $matches[1]);
        } elseif (preg_match('/(\\d{4})-(\\d{2})-(\\d{2})/', $strDate, $matches)) {
            $tstamp = mktime(0, 0, 0, (int) $matches[2], (int) $matches[3], (int) $matches[1]);
        } else {
            if (false !== strtotime($strDate)) {
                $tstamp = strtotime($strDate);
            }
        }

        return $tstamp;
    }
}
