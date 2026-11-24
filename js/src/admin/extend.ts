import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

const settingsPrefix = 'fof-first-post-approval';

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: `${settingsPrefix}.postCount`,
      label: app.translator.trans(`${settingsPrefix}.admin.settings.postCount`),
      help: app.translator.trans(`${settingsPrefix}.admin.settings.postCount_help`),
      type: 'number',
      min: 0,
      step: 1,
    }))
    .setting(() => ({
      setting: `${settingsPrefix}.discussionCount`,
      label: app.translator.trans(`${settingsPrefix}.admin.settings.discussionCount`),
      help: app.translator.trans(`${settingsPrefix}.admin.settings.discussionCount_help`),
      type: 'number',
      min: 0,
      step: 1,
    }))
    .permission(
      () => ({
        icon: 'fas fa-check',
        label: app.translator.trans(`${settingsPrefix}.admin.permissions.bypass`),
        permission: 'discussion.firstPostWithoutApproval',
      }),
      'start'
    ),

  new Extend.Conditional().whenExtensionEnabled('fof-byobu', () => [
    new Extend.Admin().setting(() => ({
      setting: `${settingsPrefix}.restrictPrivateDiscussions`,
      label: app.translator.trans(`${settingsPrefix}.admin.settings.restrictPrivateDiscussions`),
      help: app.translator.trans(`${settingsPrefix}.admin.settings.restrictPrivateDiscussions_help`),
      type: 'switch',
    })),
  ]),
];
