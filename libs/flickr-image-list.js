/***************
 * NAGINATA.fi *
 ***************
 * Juga Paazmaya <olavic@gmail.com>
 * License: Attribution-ShareAlike 3.0 Unported
 *          http://creativecommons.org/licenses/by-sa/3.0/
 */

'use strict';

/**
 * Iterate all pages for the current language and get a list of unique Flick images.
 * @returns {array.<string>} List of images
 */
module.exports = function flickrImageList() {
  var fs = require('fs');
  var path = require('path');

  // If any of the files in 'content/*/*.md' has changed, update the whole cache.
  var regex = new RegExp('\\((http:\\/\\/farm\\d+\\.static\\.?flickr\\.com\\S+\\_m.jpg)\\)', 'g');

  // Loop all Markdown files under content/*/
  var dir = path.join(__dirname, '../content/');
  var directories = fs.readdirSync(dir);
  directories = directories.filter(function dirFilter(item) {
    var stats = fs.statSync(dir + item);
    return stats.isDirectory();
  });

  var images = []; // thumbnails of Flickr images

  // Read their contents
  directories.forEach(function eachDir(directory) {
    var files = fs.readdirSync(dir + directory);
    files.forEach(function eachFile(file) {
      if (file.split('.').pop() === 'md') {
        var path = dir + directory + '/' + file;
        var content = fs.readFileSync(path, {
          encoding: 'utf8'
        });

        var matches;
        while ((matches = regex.exec(content)) !== null) {
          images.push(matches[1]);
        }
      }
    });
  });

  // Only unique
  images = images.filter(function imageFilter(e, i, arr) {
    return arr.lastIndexOf(e) === i;
  });

  //var json = JSON.stringify(images);

  return images;
};
