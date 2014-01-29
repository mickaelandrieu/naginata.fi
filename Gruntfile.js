/***************
 * NAGINATA.fi *
 ***************
 * Juga Paazmaya <olavic@gmail.com>
 * License: Attribution-ShareAlike 3.0 Unported
 *          http://creativecommons.org/licenses/by-sa/3.0/
 */

'use strict';

module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),

    phantomas: {
      hitrost: {
        options: {
          indexPath: 'phantomas/',
          raw: [
            '--film-strip',
            '--verbose'
          ],
          url: 'http://naginata.fi'
        }
      }
    },

    photobox: {
      layout: {
        options: {
          indexPath: 'photobox/',
          urls: [
            'http://naginata.fi/fi',
            'http://naginata.fi/fi/naginata',
            'http://naginata.fi/fi/koryu',
            'http://naginata.fi/fi/media',
            'http://naginata.fi/fi/yhteystiedot',
            'http://naginata.fi/en',
            'http://naginata.fi/en/naginata',
            'http://naginata.fi/en/koryu',
            'http://naginata.fi/en/media',
            'http://naginata.fi/en/contact'
          ],
          screenSizes: [
            '640', '800', '1024', '1248'
          ],
          template: 'magic'
        }
      }
    }

  });

  grunt.loadNpmTasks('grunt-phantomas');
  grunt.loadNpmTasks('grunt-photobox');

  grunt.registerTask('default', ['phantomas', 'photobox']);
};
