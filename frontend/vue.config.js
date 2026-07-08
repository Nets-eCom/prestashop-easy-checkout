const path = require('path');
const { defineConfig } = require('@vue/cli-service');

module.exports = defineConfig({
  chainWebpack: (config) => {
    // Stop generating the HTML page
    config.plugins.delete('html');
    config.plugins.delete('preload');
    config.plugins.delete('prefetch');

    // Allow resolving images in the subfolder src/assets/
    config.resolve.alias.set('@', path.resolve(__dirname, 'src'));
  },
  css: {
    extract: false,
  },
  runtimeCompiler: true,
  productionSourceMap: false,
  filenameHashing: false,
  // These rules allow the files to be compiled and stored in the proper folder
  outputDir:  path.resolve(__dirname, '../views'),
  assetsDir: '',
  // ⚠️ Change this line with your module name
  publicPath: '../modules/nexi_checkout/views/',
});
