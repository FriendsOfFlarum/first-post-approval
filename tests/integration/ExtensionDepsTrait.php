<?php

/*
 * This file is part of fof/first-post-approval.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\FirstPostApproval\Tests\integration;

trait ExtensionDepsTrait
{
    public function extensionDeps(): void
    {
        $this->extension('fof-first-post-approval');
        $this->extension('flarum-flags');
        $this->extension('flarum-approval');
        // Byobu depends on Tags, so it must be enabled first
        $this->extension('flarum-tags');
        $this->extension('fof-byobu');
    }
}
