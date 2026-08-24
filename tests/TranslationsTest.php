<?php

use Illuminate\Support\Arr;

it('keeps every translation aligned with the English translation keys', function () {
    $expectedKeys = array_keys(Arr::dot(require __DIR__ . '/../resources/lang/en/backup.php'));

    foreach (glob(__DIR__ . '/../resources/lang/*/backup.php') as $translationFile) {
        expect(array_keys(Arr::dot(require $translationFile)))
            ->toBe($expectedKeys, basename(dirname($translationFile)) . ' has missing or extra translation keys');
    }
});

it('includes the Ukrainian translation requested in issue 67', function () {
    $translation = require __DIR__ . '/../resources/lang/uk/backup.php';

    expect($translation['pages']['backups']['heading'])->toBe('Резервні копії')
        ->and($translation['components']['backup_destination_list']['table']['filters']['type'])
        ->toBe('Тип резервної копії');
});
