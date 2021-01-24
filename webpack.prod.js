const path = require('path');
const common = require('./webpack.common');
const merge = require('webpack-merge');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const CopyPlugin = require('copy-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const HtmlWebpackPlugin = require('html-webpack-plugin');

module.exports = merge(common, {
	mode    : 'production',
	plugins : [
		new MiniCssExtractPlugin({ filename: '[name].[contentHash].css' }),
    new CleanWebpackPlugin(),
    new HtmlWebpackPlugin({
      template : './src/template.html',
      minify: {
        collapseWhitespace: true,
        removeComments: true
      }
		}),
		new CopyPlugin([
      { from: './src/static', to: path.resolve(__dirname, 'dist/static'), ignore: [ '.DS_Store' ] },
      { from: './src/.htaccess', to: path.resolve(__dirname, 'dist') + '/.htaccess', toType: 'file' },
      { from: './src/robots.txt', to: path.resolve(__dirname, 'dist') + '/robots.txt', toType: 'file' },
			{
				from: './src/parsers/php/guzzle',
				to: path.resolve(__dirname, 'dist/parsers/php/guzzle'),
				ignore: [ '.DS_Store' ]
			}
		])
	],
	output  : {
		filename : 'main.[contentHash].js',
		path     : path.resolve(__dirname, 'dist')
	},
	module  : {
		rules : [
			{
				test : /\.sass$/,
				use  : [ MiniCssExtractPlugin.loader, 'css-loader', 'sass-loader' ] // loads in reverse
			}
		]
	}
});
