import app from 'flarum/admin/app';

export const settingsPrefix = 'fof-first-post-approval';

app.initializers.add(settingsPrefix, () => {
  const extensionData = app.registry
    .for(settingsPrefix)
    .registerSetting({
      setting: `${settingsPrefix}.postCount`,
      label: app.translator.trans(`${settingsPrefix}.admin.settings.postCount`),
      help: app.translator.trans(`${settingsPrefix}.admin.settings.postCount_help`),
      type: 'number',
      min: 0,
      step: 1,
    })
    .registerSetting({
      setting: `${settingsPrefix}.discussionCount`,
      label: app.translator.trans(`${settingsPrefix}.admin.settings.discussionCount`),
      help: app.translator.trans(`${settingsPrefix}.admin.settings.discussionCount_help`),
      type: 'number',
      min: 0,
      step: 1,
    });

  // Only relevant when Byobu is installed and enabled
  if (app.initializers.has('fof-byobu')) {
    extensionData.registerSetting({
      setting: `${settingsPrefix}.restrictPrivateDiscussions`,
      label: app.translator.trans(`${settingsPrefix}.admin.settings.restrictPrivateDiscussions`),
      help: app.translator.trans(`${settingsPrefix}.admin.settings.restrictPrivateDiscussions_help`),
      type: 'switch',
    });
  }

  extensionData.registerPermission(
    {
      icon: 'fas fa-check',
      label: app.translator.trans(`${settingsPrefix}.admin.permissions.bypass`),
      permission: 'discussion.firstPostWithoutApproval',
    },
    'start'
  );
});
