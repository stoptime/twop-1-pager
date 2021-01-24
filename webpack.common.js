//const path = require('path');
const Dotenv = require('dotenv-webpack');

module.exports = {
	node   : {
		fs: 'empty' // fix for dotenv and webpack
	},
	entry  : './src/index.js',
	module : {
		rules : [
			{
				test    : /\.js$/,
				exclude : /node_modules/,
				loader  : 'babel-loader',
				options : {
					plugins : [ '@babel/plugin-proposal-class-properties' ]
				}
			},
			{
				test : /\.html$/,
				use  : [ 'html-loader' ]
			},
			{
				test : /\.(svg|png|jpg|jpeg|gif)$/,
				use  : [
					{
						loader  : 'file-loader',
						options : {
							outputPath : 'static/img'
						}
					}
				]
			}
		]
	},
	plugins: [
		new Dotenv()
	]
};
