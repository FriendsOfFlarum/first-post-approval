<?php

/*
 * This file is part of fof/first-post-approval.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Builder;

/**
 * Carries settings over from clarkwinkelmann/flarum-ext-first-post-approval.
 *
 * Permissions do not need migrating: `discussion.firstPostWithoutApproval` is
 * stored unprefixed and is unchanged between the two extensions.
 */
$oldPrefix = 'clarkwinkelmann-first-post-approval.';
$newPrefix = 'fof-first-post-approval.';

$settings = [
    'postCount',
    'discussionCount',
];

return [
    'up' => function (Builder $schema) use ($oldPrefix, $newPrefix, $settings) {
        $db = $schema->getConnection();

        foreach ($settings as $setting) {
            $old = $db->table('settings')->where('key', $oldPrefix.$setting)->first();

            // Nothing to migrate, or the admin has already configured the new setting
            if ($old === null || $db->table('settings')->where('key', $newPrefix.$setting)->exists()) {
                continue;
            }

            $db->table('settings')->insert([
                'key'   => $newPrefix.$setting,
                'value' => $old->value,
            ]);
        }
    },
    'down' => function (Builder $schema) use ($newPrefix, $settings) {
        $db = $schema->getConnection();

        foreach ($settings as $setting) {
            $db->table('settings')->where('key', $newPrefix.$setting)->delete();
        }
    },
];
