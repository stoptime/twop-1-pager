const path = require('path');
const common = require('./webpack.common');
const merge = require('webpack-merge');
const HtmlWebpackPlugin = require('html-webpack-plugin');

module.exports = merge(common, {
	mode   : 'development',
	output : {
		filename : 'main.js',
		path     : path.resolve(__dirname, 'dist')
  },
  plugins : [
		new HtmlWebpackPlugin({
			template : './src/template.html'
		})
	],
	module : {
		rules : [
			{
				test : /\.sass$/,
				use  : [ 'style-loader', 'css-loader', 'sass-loader' ] // loads in reverse
			}
		]
	}
});
