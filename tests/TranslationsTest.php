<?php

use Illuminate\Support\Arr;

it('keeps every translation aligned with the English translation keys', function () {
    $expectedKeys = array_keys(Arr::dot(Arr::wrap(require __DIR__ . '/../resources/lang/en/backup.php')));
    $translationFiles = glob(__DIR__ . '/../resources/lang/*/backup.php');

    if ($translationFiles === false) {
        throw new RuntimeException('Unable to discover translation files.');
    }

    foreach ($translationFiles as $translationFile) {
        expect(array_keys(Arr::dot(Arr::wrap(require $translationFile))))
            ->toBe($expectedKeys, basename(dirname($translationFile)) . ' has missing or extra translation keys');
    }
});

it('includes the Ukrainian translation requested in issue 67', function () {
    $translation = require __DIR__ . '/../resources/lang/uk/backup.php';

    expect(data_get($translation, 'pages.backups.heading'))->toBe('Резервні копії')
        ->and(data_get($translation, 'components.backup_destination_list.table.filters.type'))
        ->toBe('Тип резервної копії');
});
