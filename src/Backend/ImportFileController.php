<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Backend;

use Cgoit\ContaoCalendarIcalBundle\Import\CsvImport;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Cgoit\ContaoCalendarIcalBundle\Util\TimezoneUtilAwareInterface;
use Cgoit\ContaoCalendarIcalBundle\Widgets\WidgetTrait;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarModel;
use Contao\Config;
use Contao\DataContainer;
use Contao\Date;
use Contao\Environment;
use Contao\File;
use Contao\FileUpload;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ImportFileController extends Backend implements TimezoneUtilAwareInterface
{
    use WidgetTrait;

    private string $filterEventTitle;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $csrfTokenName,
        private readonly IcsImport $icsImport,
        private readonly CsvImport $csvImport,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    public function importCalendar(DataContainer $dc): string
    {
        if (!$dc->id) {
            return '';
        }

        $objCalendar = CalendarModel::findById($dc->id);
        if (null === $objCalendar) {
            return '';
        }

        if ('import' !== Input::get('key')) {
            return '';
        }

        $class = BackendUser::getInstance()->uploader;

        // See #4086
        if (!class_exists($class)) {
            $class = FileUpload::class;
        }

        static::loadLanguageFile('tl_calendar_events');
        static::loadLanguageFile('tl_files');
        $objTemplate = new BackendTemplate('be_import_calendar');

        $objTemplate->request_token = $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue();

        $objUploader = new $class();
        $objTemplate->markup = $objUploader->generateMarkup();
        $objTemplate->icssource = $this->getFileTreeWidget();
        $year = date('Y', time());
        $defaultTimeShift = 0;
        $tstamp = mktime(0, 0, 0, 1, 1, (int) $year);
        $defaultStartDate = date(Config::get('dateFormat'), $tstamp);
        $tstamp = mktime(0, 0, 0, 12, 31, (int) $year);
        $defaultEndDate = date(Config::get('dateFormat'), $tstamp);
        $objTemplate->startDate = $this->getStartDateWidget($defaultStartDate);
        $objTemplate->endDate = $this->getEndDateWidget($defaultEndDate);
        $objTemplate->timeshift = $this->getTimeShiftWidget($defaultTimeShift);
        $objTemplate->deleteCalendar = $this->getDeleteWidget();
        $objTemplate->filterEventTitle = $this->getFilterWidget();
        $objTemplate->max_file_size = Config::get('maxFileSize');
        $objTemplate->message = Message::generate();

        $objTemplate->hrefBack = StringUtil::ampersand(str_replace('&key=import', '', (string) Environment::get('request')));
        $objTemplate->goBack = $GLOBALS['TL_LANG']['MSC']['goBack'];
        $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['import_calendar'][0];
        $objTemplate->request = StringUtil::ampersand(Environment::get('request'), true);
        $objTemplate->submit = StringUtil::specialchars($GLOBALS['TL_LANG']['tl_calendar_events']['import'][0]);

        // Create import form
        if ('tl_import_calendar' === Input::post('FORM_SUBMIT') && $this->blnSave) {
            $arrUploaded = $objUploader->uploadTo('system/tmp');

            if (empty($arrUploaded)) {
                Message::addError($GLOBALS['TL_LANG']['ERR']['all_fields']);
                static::reload();
            }

            $arrFiles = [];

            foreach ($arrUploaded as $strFile) {
                // Skip folders
                if (is_dir($this->projectDir.'/'.$strFile)) {
                    Message::addError(\sprintf($GLOBALS['TL_LANG']['ERR']['importFolder'], basename((string) $strFile)));
                    continue;
                }

                $objFile = new File($strFile);

                if ('ics' !== $objFile->extension && 'csv' !== $objFile->extension) {
                    Message::addError(\sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $objFile->extension));
                    continue;
                }

                $arrFiles[] = $strFile;
            }

            if (empty($arrFiles)) {
                Message::addError($GLOBALS['TL_LANG']['ERR']['all_fields']);
                static::reload();
            } else {
                if (\count($arrFiles) > 1) {
                    Message::addError($GLOBALS['TL_LANG']['ERR']['only_one_file']);
                    static::reload();
                } else {
                    $startDate = new Date($objTemplate->startDate->value, Config::get('dateFormat'));
                    $endDate = new Date($objTemplate->endDate->value, Config::get('dateFormat'));
                    $deleteCalendar = (bool) $objTemplate->deleteCalendar->value;
                    $this->filterEventTitle = $objTemplate->filterEventTitle->value;
                    $timeshift = (int) $objTemplate->timeshift->value;
                    $file = new File($arrFiles[0]);
                    if ('ics' === $file->extension) {
                        return $this->importFromICSFile($file->path, $objCalendar, $startDate, $endDate, null, null, $deleteCalendar,
                            $timeshift);
                    }
                    if ('csv' === $file->extension) {
                        $this->requestStack
                            ->getCurrentRequest()
                            ->getSession()
                            ->set('calendar_ical.csvimport.settings', serialize([
                                'csv_pid' => $dc->id,
                                'csv_timeshift' => $objTemplate->timeshift->value,
                                'csv_startdate' => $objTemplate->startDate->value,
                                'csv_enddate' => $objTemplate->endDate->value,
                                'csv_deletecalendar' => $deleteCalendar,
                                'csv_filterEventTitle' => $this->filterEventTitle,
                                'csv_filename' => $file->path,
                            ]))
                        ;

                        return $this->csvImport->importFromCSVFile();
                    }
                }
            }
        } elseif ('tl_import_calendar_confirmation' === Input::post('FORM_SUBMIT') && $this->blnSave) {
            $startDate = new Date(Input::post('startDate'), Config::get('dateFormat')); // @phpstan-ignore-line
            $endDate = new Date(Input::post('endDate'), Config::get('dateFormat')); // @phpstan-ignore-line
            $filename = Input::post('icssource');
            $deleteCalendar = (bool) Input::post('deleteCalendar');
            $this->filterEventTitle = Input::post('filterEventTitle');
            $timeshift = (int) Input::post('timeshift');

            if (!empty(Input::post('timezone'))) {
                $timezone = Input::post('timezone');
                $correctTimezone = null;
            } else {
                $timezone = null;
                $correctTimezone = (bool) Input::post('correctTimezone');
            }

            return $this->importFromICSFile($filename, $objCalendar, $startDate, $endDate, $correctTimezone, $timezone,
                $deleteCalendar, $timeshift);
        } elseif ('tl_csv_headers' === Input::post('FORM_SUBMIT')) {
            if ($this->blnSave && !empty(Input::post('import'))) {
                return $this->csvImport->importFromCSVFile(false);
            }

            return $this->csvImport->importFromCSVFile();
        }

        return $objTemplate->parse();
    }

    public function getConfirmationForm(string $icssource, Date $startDate, Date $endDate, string|null $tzimport, string $tzsystem, bool $deleteCalendar): string
    {
        $objTemplate = new BackendTemplate('be_import_calendar_confirmation');

        $objTemplate->request_token = $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue();

        if (!empty($tzimport)) {
            $objTemplate->confirmationText = \sprintf(
                $GLOBALS['TL_LANG']['tl_calendar_events']['confirmationTimezone'],
                $tzsystem,
                $tzimport,
            );
            $objTemplate->correctTimezone = $this->getCorrectTimezoneWidget();
        } else {
            $objTemplate->confirmationText = \sprintf(
                $GLOBALS['TL_LANG']['tl_calendar_events']['confirmationMissingTZ'],
                $tzsystem,
            );
            $objTemplate->timezone = $this->getTimezoneWidget($tzsystem);
        }

        $objTemplate->startDate = $startDate->date;
        $objTemplate->endDate = $endDate->date;
        $objTemplate->icssource = $icssource;
        $objTemplate->deleteCalendar = $deleteCalendar;
        $objTemplate->filterEventTitle = $this->filterEventTitle;
        $objTemplate->hrefBack = StringUtil::ampersand(str_replace('&key=import', '', (string) Environment::get('request')));
        $objTemplate->goBack = $GLOBALS['TL_LANG']['MSC']['goBack'];
        $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['import_calendar'][0];
        $objTemplate->request = StringUtil::ampersand(Environment::get('request'));
        $objTemplate->submit = StringUtil::specialchars($GLOBALS['TL_LANG']['tl_calendar_events']['proceed'][0]);

        return $objTemplate->parse();
    }

    private function importFromICSFile(string $filename, CalendarModel $objCalendar, Date $startDate, Date $endDate, bool|null $correctTimezone = null, string|null $manualTZ = null, bool $deleteCalendar = false, int $timeshift = 0): string
    {
        $cal = new Vcalendar();
        $cal->setMethod(Vcalendar::PUBLISH);
        $cal->setXprop(Vcalendar::X_WR_CALNAME, $objCalendar->title);
        $cal->setXprop(Vcalendar::X_WR_CALDESC, $objCalendar->title);

        try {
            $file = new File($filename);
            $content = $file->exists() ? $file->getContent() : '';
            if (empty($content)) {
                throw new \InvalidArgumentException('Ical content empty');
            }
            $cal->parse($content);
        } catch (\Throwable $e) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['ics_parse_error']);
            System::getContainer()
                ->get('monolog.logger.contao.general')
                ->error('Could not import ics file "'.$filename.'": '.$e->getMessage())
            ;

            static::reload();
        }

        $tz = $cal->getXprop(IcalInterface::X_WR_TIMEZONE);
        if (false === $tz && null !== $tzComponent = $cal->getComponent(IcalInterface::VTIMEZONE)) {
            $tz = $tzComponent->getXprop(IcalInterface::X_LIC_LOCATION);
        }

        // if (0 === $timeshift) {
        if (\is_array($tz) && !empty($tz[1]) && (string) $tz[1] !== (string) Config::get('timeZone')) {
            if (null === $correctTimezone) {
                return $this->getConfirmationForm($filename, $startDate, $endDate, $tz[1],
                    $GLOBALS['TL_CONFIG']['timeZone'], $deleteCalendar);
            }
        } elseif (!\is_array($tz) || empty($tz[1])) {
            if (null === $manualTZ) {
                return $this->getConfirmationForm($filename, $startDate, $endDate, null,
                    $GLOBALS['TL_CONFIG']['timeZone'], $deleteCalendar);
            }
        }

        if (!empty($manualTZ)) {
            if (\is_array($tz)) {
                $tz[1] = $manualTZ;
            } else {
                $tz = [$manualTZ, $manualTZ];
            }
        }
        // }

        $this->icsImport->importFromIcsFile($cal, $objCalendar, $startDate, $endDate, $tz, $this->filterEventTitle, null, null, $deleteCalendar, $timeshift);
        static::redirect(str_replace('&key=import', '', (string) Environment::get('request')));

        return '';
    }
}
