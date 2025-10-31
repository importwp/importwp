/**
 * External Dependencies
 */
const path = require('path');

const defaults = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaults,
    externals: {
        react: 'React',
        'react-dom': 'ReactDOM',
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
    }
}; 