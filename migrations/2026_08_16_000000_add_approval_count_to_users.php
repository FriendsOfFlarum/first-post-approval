<?php

/*
 * This file is part of fof/first-post-approval.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * The columns are added conditionally because forums upgrading from
 * clarkwinkelmann/flarum-ext-first-post-approval already have them, but none of
 * that extension's migrations are recorded against this extension's ID.
 */
$columns = [
    'first_post_approval_count'       => ['tinyInteger', 'unsigned' => true, 'default' => 0],
    'first_discussion_approval_count' => ['tinyInteger', 'unsigned' => true, 'default' => 0],
];

return [
    'up' => function (Builder $schema) use ($columns) {
        $missing = array_filter(
            $columns,
            fn (string $columnName) => !$schema->hasColumn('users', $columnName),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($missing)) {
            return;
        }

        $schema->table('users', function (Blueprint $table) use ($missing) {
            foreach ($missing as $columnName => $options) {
                $type = array_shift($options);
                $table->addColumn($type, $columnName, $options);
            }
        });
    },
    'down' => function (Builder $schema) use ($columns) {
        $existing = array_filter(
            array_keys($columns),
            fn (string $columnName) => $schema->hasColumn('users', $columnName)
        );

        if (empty($existing)) {
            return;
        }

        $schema->table('users', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    },
];
