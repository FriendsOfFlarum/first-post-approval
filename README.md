# First Post Approval by FriendsOfFlarum

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/first-post-approval.svg)](https://packagist.org/packages/fof/first-post-approval) [![Total Downloads](https://img.shields.io/packagist/dt/fof/first-post-approval.svg)](https://packagist.org/packages/fof/first-post-approval) [![OpenCollective](https://img.shields.io/badge/opencollective-fof-blue.svg)](https://opencollective.com/fof/donate)

A [Flarum](http://flarum.org) extension. Hold the first n posts and/or discussions from new users for approval.

## Installation

```sh
composer require fof/first-post-approval
```

Flarum's **Approval** and **Flags** extensions must also be enabled.

## Updating

```sh
composer update fof/first-post-approval
php flarum migrate
php flarum cache:clear
```

## Documentation

Some groups can be excluded from the rule on the permissions page.

When a post is approved, it counts +1 towards the number of first posts to approve.

When a discussion is approved, it counts +1 towards the number of first discussions to approve, and also +1 towards the number of first posts.

If you don't set a number of discussions to approve, new discussions will be held for approval based on the number of posts of the user.
For example if you require 2 posts to be approved but 0 discussions, if one of the first two interactions of the user is to create a discussion, that discussion will be held for approval.
But if they first create two replies that get approved, they can then create their first discussion without approval.

### Existing users

If you install this extension on a forum with an existing user base, you might want to manually update the `first_post_approval_count` and `first_discussion_approval_count` columns on the `users` table to prevent existing users from being subjected to the first post approval.
Any number equal or higher than the number configured in the extension settings will cause the approval to be skipped.

Alternatively, you can exclude some groups on the permissions page.

### FriendsOfFlarum Byobu

Private discussions cannot be held for approval, so if the [FriendsOfFlarum Byobu](https://github.com/FriendsOfFlarum/byobu) extension is enabled, users who are still subject to first post approval are prevented from starting them. This is enforced by the API, not just hidden in the interface.

If you would rather let new users message each other freely, turn off **Prevent private discussions until approved** in the extension settings.

## Credits

This extension was originally developed by [Clark Winkelmann](https://clarkwinkelmann.com/) for a client and released as open-source. It has since been adopted and is now maintained by [FriendsOfFlarum](https://friendsofflarum.org/).

## Links

[![OpenCollective](https://img.shields.io/badge/donate-friendsofflarum-44AEE5?style=for-the-badge&logo=open-collective)](https://opencollective.com/fof/donate)

- [Packagist](https://packagist.org/packages/fof/first-post-approval)
- [GitHub](https://github.com/FriendsOfFlarum/first-post-approval)
- [Discuss](https://discuss.flarum.org/d/39689)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum).
