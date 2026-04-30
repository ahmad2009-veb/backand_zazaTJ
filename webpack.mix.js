const mix = require('laravel-mix');

// mix.scripts([
// ], 'public/assets/web/js/bundle.js');

mix
  .js('resources/js/address.js', 'public/assets/web/js')
  .js('resources/js/admin/pos.js', 'public/assets/admin/js/')
  .js('resources/js/admin/edit-user.js', 'public/assets/admin/js/')
  .vue()
  .webpackConfig(require('./webpack.config'));

mix.css('resources/css/custom.css', 'public/assets/web/css');

if (mix.inProduction()) {
  mix.version();
}
