/**
 * External Dependencies
 */
const defaults = require('./webpack.config.js');

module.exports = {
    ...defaults,
    optimization: {
        ...defaults.optimization,
        minimize: false
    }
}