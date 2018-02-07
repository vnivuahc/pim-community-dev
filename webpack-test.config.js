/* eslint-env es6 */
const rootDir = process.cwd();
const webpack = require('webpack');
const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const HtmlWebpackInlineSourcePlugin = require('html-webpack-inline-source-plugin');
const prodConfig = require('./webpack.config.js');

const plugins = [
    new HtmlWebpackPlugin({
        inject: 'head',
        template: './web/bundles/pimfront/test/integration/index.html',
        minify: {},
        inlineSource: '.(js)$'
    }),
    ...prodConfig.plugins,
    new HtmlWebpackInlineSourcePlugin()
];

module.exports = Object.assign({}, prodConfig, {
    entry: [
        'babel-polyfill',
        path.resolve(rootDir, './web/bundles/pimfront/test/integration/index.js')
    ],
    output: {
        path: path.resolve('./web/test_dist/'),
        publicPath: '/dist/',
        filename: '[name].min.js',
        chunkFilename: '[name].bundle.js'
    },

    plugins: plugins
});
