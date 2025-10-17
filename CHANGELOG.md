# Changelog

## [5.9.8](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.9.7...v5.9.8) (2025-10-16)


### Bug Fixes

* recurrence count calculation ([#96](https://github.com/cgoIT/contao-calendar-ical-bundle/issues/96)) ([67e4493](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/67e449365b24c35060a8f75fda59228ca9abf959))

## [5.9.7](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.9.6...v5.9.7) (2025-09-15)


### Bug Fixes

* fix route registration in contao 5 ([454ccd7](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/454ccd73df9c54aa08f10dfef5b90ac19978c366))

## [5.9.6](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.9.5...v5.9.6) (2025-09-12)


### Bug Fixes

* add empty check for arrRepeat ([2900025](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/290002572e85f58bf8c1ace0f6c87747632d9ca4))
* add int typecasts for date() ([a222f05](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/a222f0514a9663a56e49533cfc4dbe626fe5ab93))

## [5.9.5](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.9.4...v5.9.5) (2025-08-26)


### Bug Fixes

* Fix error on deleting Calendar in Version 4.13 ([#88](https://github.com/cgoIT/contao-calendar-ical-bundle/issues/88)) ([9fe8633](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/9fe8633104724aa92b3048f9f7ce41373595f728))

## [5.9.4](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.9.3...v5.9.4) (2025-08-19)


### Miscellaneous Chores

* update version contraints ([86e23e8](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/86e23e8f95814d35a70917ff62d1da79c9c910ed))

## [5.9.3](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.9.2...v5.9.3) (2025-07-10)


### Bug Fixes

* disable RenameAttributeRector for route classes to support custom export routes on Contao 4 and 5 ([3bcc2ae](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/3bcc2aed124ad683fe191dd852f4504fdb51bcd7))

## [5.9.2](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.9.1...v5.9.2) (2025-06-13)


### Bug Fixes

* change implementation for file download response handling ([e28e23d](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/e28e23dea7b397f828cd4d600a4f4302f54aabc0))

## [5.9.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.9.0...v5.9.1) (2025-06-12)


### Bug Fixes

* objPage is null ([694b3f4](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/694b3f48c7910f8702e5e8cbc43404a43faff98e))

## [5.9.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.8.0...v5.9.0) (2025-06-12)


### Features

* make an eventlist module exportable as ical file via custom frontend route ([8530b90](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/8530b9073f38853e0b5451908f10779443814cd6))

## [5.8.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.7.2...v5.8.0) (2025-04-29)


### Features

* add AfterImportitem event ([ace9d36](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/ace9d36ffa159d5c3e7895f11a64e11dd74064ce))
* add BeforeImportItemEvent ([d5c6bd9](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/d5c6bd9a85b8db662c8985bd1b517c4386e85c50))

## [5.7.2](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.7.1...v5.7.2) (2025-04-12)


### Bug Fixes

* fixes a Turbo.js error while csv-import ([f1857f2](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/f1857f2d38ead083edb73f5d6b7d1d66b264efc0))

## [5.7.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.7.0...v5.7.1) (2025-03-21)


### Bug Fixes

* fix error 'The filename fallback must only contain ASCII characters' ([81580ec](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/81580ec4cbf55e6ddaa54917e1d2de89ab6e05d5))
* fix error 'The filename fallback must only contain ASCII characters' ([846eb7c](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/846eb7ca96342ff377765e2f5062b691325d0687))

## [5.7.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.6.0...v5.7.0) (2025-03-20)


### Features

* add hook `icalModifyVevent` to be able to further adjust the exported VEvent ([1c177ea](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/1c177ea3ed7e3989be682d85e6c5833d53a0133d))


### Bug Fixes

* allow multiple download elements on one page ([14bbf38](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/14bbf381ee7cc2415c7ed346b2fdfcd35d91c1ef))
* stream iCal without temporary file ([412e78f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/412e78f4b72d6029878ccb95c247866a6bb17f3c))

## [5.6.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.5.1...v5.6.0) (2025-03-15)


### Features

* automatically add /_event/ to disallowed urls in robots.txt ([1711299](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/171129977df2076da571714c286d0a2a51786b4b))

## [5.5.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.5.0...v5.5.1) (2025-03-13)


### Bug Fixes

* fix iCal Download content element ([21fd72f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/21fd72fde3d4c502127ef8caf01c922fd5bd5253))
* fix phpstan errors ([4388edb](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4388edb58ec8d3e04f3567d1805deedc40ed1c49))

## [5.5.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.4.0...v5.5.0) (2025-01-28)


### Features

* add an option to export a single event as ics ([b7e166d](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/b7e166dea47a954ebd81ff323725b1ed9f5c6164))

## [5.4.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.3.7...v5.4.0) (2024-12-03)


### Features

* handle recurring events with until date correctly ([28842c7](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/28842c775bdaf53e902dfc416ca6f705bbefbb6d))


### Bug Fixes

* handle case when UNTIL date is before event start date ([643a3d4](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/643a3d4f018bac4e1e94559ed271ec5bed7df91b))


### Miscellaneous Chores

* fix ecs errors ([d7a9905](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/d7a9905a0a57470d1f571e1b14020383a5f80612))
* use Doctrine Schema Representation for db columns ([bc1da6f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/bc1da6f07020dfbf3f3062bb73d5f0b14c96e324))

## [5.3.7](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.3.6...v5.3.7) (2024-11-11)


### Bug Fixes

* fix date handling in ics export for events without time part ([ad674eb](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/ad674eb5c5321a1d3d6e7903b417fe0add5b85d7))

## [5.3.6](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.3.5...v5.3.6) (2024-10-31)


### Bug Fixes

* add support for updating event exceptions with new times ([f5d8e66](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/f5d8e66704ebf3de56d00baf4a90da8e311c554e))

## [5.3.5](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.3.4...v5.3.5) (2024-09-06)


### Bug Fixes

* only write real errors with level error ([03ef59d](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/03ef59d5f178309affa22c22809ab6d382b41a16))


### Miscellaneous Chores

* fix ecs bugs ([b17d548](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/b17d5486d42f59026207d2f658beab56651c76fb))
* fix phpstan bugs ([65168ca](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/65168ca05e070177d69fce460829fa64e6176526))

## [5.3.4](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.3.3...v5.3.4) (2024-06-07)


### Bug Fixes

* add advanced repeating event support to calendar export ([4bb4f00](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4bb4f00249df6126f6905f118f2aa1d575142a2d))
* remove CalendarEventsModelExt and update event fetching in IcsExport ([c6461a5](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/c6461a529f6ce25bfa593263f5a71408b7f4347b))

## [5.3.3](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.3.2...v5.3.3) (2024-05-13)


### Miscellaneous Chores

* fix ecs findings ([4ad2007](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4ad20078c4d542b1e4b899fbf4f4170b9e88f98f))

## [5.3.2](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.3.1...v5.3.2) (2024-05-13)


### Bug Fixes

* fix InvalidArgumentException, fixes [#49](https://github.com/cgoIT/contao-calendar-ical-bundle/issues/49) ([993ec62](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/993ec62f682c03248c42d6e605e0260290938aaa))

## [5.3.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.3.0...v5.3.1) (2024-05-08)


### Bug Fixes

* initialize contao framework before loading language files ([3a90ada](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/3a90adabd80aeccc20c1f4dec1a7356acd787f6e))

## [5.3.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.2.5...v5.3.0) (2024-05-02)


### Features

* better reoccurence handling ([8958c68](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/8958c68cc9b70cbae284bb51ad8ef83c0939f48f))


### Miscellaneous Chores

* add shadow dependencies ([88ad63a](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/88ad63a8918fe19e78566c9892c96e0ca92ac558))
* minor adjustments in composer.json ([d0ba8b3](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/d0ba8b310229a738639e1355a2b2669c68cab894))

## [5.2.5](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.2.4...v5.2.5) (2024-04-25)


### Bug Fixes

* set all text columns to 'text NULL' since mysql doesn't support default values for text columns in strict mode ([96fed30](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/96fed3075147385cb5a26e9e3aa8ced562a80b3f))


### Miscellaneous Chores

* fix ecs errors ([55fb3b5](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/55fb3b5c99ed4f5b46f92d9eda6d72f56ae21925))

## [5.2.4](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.2.3...v5.2.4) (2024-04-23)


### Bug Fixes

* use custom download template for ical ce only in the frontend, fixes [#43](https://github.com/cgoIT/contao-calendar-ical-bundle/issues/43) ([82081c5](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/82081c509e769cbae7649e3a293b14f9437f73de))

## [5.2.3](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.2.2...v5.2.3) (2024-04-11)


### Bug Fixes

* fix sql error SQLSTATE[23000]: Integrity constraint violation: 1052 Column 'id' in where clause is ambiguous ([b0fc158](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/b0fc158e887dc5946faa3f68a93a1a29589129e7))


### Miscellaneous Chores

* fix ecs errors ([88033c8](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/88033c8dc6ff473c5c6cd6b952e6879189a70103))

## [5.2.2](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.2.1...v5.2.2) (2024-04-09)


### Bug Fixes

* timeEnd of event could be null ([3a59e99](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/3a59e996c66cd95d4347c06875d90d4b590ab253))

## [5.2.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.2.0...v5.2.1) (2024-04-05)


### Bug Fixes

* change implementation to be compatible with contao 4 ([c4f1de4](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/c4f1de488eafff85b6f43574c3d28da6687d027b))

## [5.2.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.1.3...v5.2.0) (2024-04-04)


### Features

* new page type "ics feed" ([182da03](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/182da039ce577b2066ee7b420cd0fb83d854ae6b))


### Bug Fixes

* add custom page icon for page type "ics feed" ([344d5db](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/344d5db0c88951cdfeadb37aeb664c979d06585e))

## [5.1.3](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.1.2...v5.1.3) (2024-04-04)


### Bug Fixes

* change column types to prevent "row to large" errors ([178d37a](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/178d37aff4938d868dcf598f0ea7a23cca9b0e87))

## [5.1.2](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.1.1...v5.1.2) (2024-04-02)


### Bug Fixes

* initialize contao framework in cron jobs ([5c5eb57](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/5c5eb57c7ac7fa686357c4399c9c8934d9724295))

## [5.1.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.1.0...v5.1.1) (2024-03-06)


### Bug Fixes

* fix some minor issues ([a66e841](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/a66e8414c198712dccb6f6f8026ec63c35aecb4f))

## [5.1.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.0.2...v5.1.0) (2024-03-05)


### Features

* better handling for existing events. events are not fully deleted and reimported but mapped via the event uid, fix ecs and phpstan findings ([0fe487b](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/0fe487bbb68bdc5df56fdf0ef46796f1a11405f4))


### Bug Fixes

* ecs and phpstan findings ([10739cc](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/10739cc3bcc0d144257b14c0f4c3ec687348131b))


### Miscellaneous Chores

* automatic rector changes ([d41ed3c](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/d41ed3cfd0b5ad6570f201eda0104c0bfc193b9f))
* **ci:** add php 8.3 to version matrix ([e0c8f12](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/e0c8f12cee00db18a0746d9d12de8f996f942fd5))
* fix phpstan errors ([ed2998f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/ed2998f31209d79e46b8b9086e873b0071185b4a))

## [5.0.2](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.0.1...v5.0.2) (2023-11-28)


### Bug Fixes

* don't store the uid in the description field ([4a0af93](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4a0af93e1603e484f90b30ab8c2011021fb6011b))

## [5.0.1](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/v5.0.0...v5.0.1) (2023-11-26)


### Bug Fixes

* default values are closures in contao 5 ([ddedb47](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/ddedb4718bf57cf49f951539fb503ff748758da8))
* fix database migration via contao-manager in contao 5 ([31ac4ac](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/31ac4ac93664e3ba93643f064543cb72b9bf6b18))

## [5.0.0](https://github.com/cgoIT/contao-calendar-ical-bundle/compare/4.5.1...v5.0.0) (2023-11-25)


### Features

* add github ci and release-please ([2b04aab](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/2b04aabaa45da8cd8ec21f9758600004b6ef7877))
* add regenerate ics files to backend maintenance module ([f288388](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/f2883882eaa563214c44403b5dbe9957a92671d6))
* export as ics file, delete ics file if calendar is deleted ([83081d1](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/83081d1d856f63f2dcc1eee8c7af3120205cae49))


### Bug Fixes

* add backend assets via EventSubscriber ([6d66793](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/6d667938170de806435101ecfc3d1b49dda6c8b0))
* change icon ([21d6dc4](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/21d6dc455b0b76b6c35ad0e26b73262fd12dd7a4))
* copy link to clipboard fix for contao 5 ([c052b34](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/c052b34ed317d023714b179289ba2a361e5dcc90))
* default values are closures in contao 5 ([7a34e1b](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/7a34e1bde7a073e46b091dc63888c2d9fb01d7eb))
* ecs and phpstan fixes ([391e851](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/391e85152ef8ea50acaae8688e2fa717d15eeed7))
* fix ecs errors ([8cb6e38](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/8cb6e38291f2dabed3cdb669ec2d11bb747b6e6e))
* fix error during ics cache handling ([5436404](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/5436404fc8c3317d8d9064a29eb6903ea0f647da))
* fix error in csv import ([a617f98](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/a617f9884a0cff0e0e2ee5d1e4807c017d2c6063))
* fix ical download element ([182d6ec](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/182d6ec3b098f1d35b1f4f9e8f2e0dc865feba2b))
* fix ical download element ([094ef79](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/094ef7902b475ec603303ca4acb81a430cfbf407))
* fix ics import ([536f69f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/536f69fa4d038f8d30986d4fcf0cf5ebd9357689))
* fix phpstan errors ([067783f](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/067783f30c114992e21d70523272986096a2fdc3))
* fix some minor issues ([4f60f9c](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4f60f9c73b0537325f305db9a952b1fbfba4d67d))
* multiple fixes for ics export ([a587c80](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/a587c8002cd36769d12ea45a9469d2322ae7440f))
* only export event if a startDate (or time) is present ([f9d5558](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/f9d5558018527d84564bffc7d0576df9e4165b98))
* small fix for ics import ([28fb22c](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/28fb22ca2520408034701610767e1d52c8c31920))


### Miscellaneous Chores

* add github templates ([4257b4a](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/4257b4a94359350e730d80dbc455a00fb693f803))
* bundle is loading fine, dca seems to be correct ([778c5eb](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/778c5eb48720a18fe9ca2afa813f761e2741b6c9))
* change .gitignore ([3b3fa26](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/3b3fa26cc13e99ff53327348a63857251e83c3cf))


### Documentation

* latest fixes to README ([9f2f15b](https://github.com/cgoIT/contao-calendar-ical-bundle/commit/9f2f15b40a13d0fc81e7e5ab56c1fae22158ecfd))
