require('@wordpress/jest-preset-default/scripts/setup-globals.js');

global.window = global.window || {};

window.iwp = {
  admin_base: '/wp-admin/admin.php?page=importwp',
  ajax_base: '/wp-admin/admin-ajax.php',
  is_debug: 'no',
  hooks: {
    applyFilters: (_name, value) => value,
    addFilter: () => {},
  },
};
