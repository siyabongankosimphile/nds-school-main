
const path = require('path');

module.exports = {
  entry: './assets/js/frontend.js', // Your entry JS file
  output: {
    filename: 'assets/js/bootstrap.bundle.js', // The output bundled JS file
    path: path.resolve(__dirname, 'dist'), // The output folder
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env'],
          },
        },
      },
    ],
  },
  mode: 'production', // Production mode for minification
};