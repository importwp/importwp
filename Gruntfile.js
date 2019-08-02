module.exports = function(grunt) {

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        copy: {
            main: {
                files: [
                    {expand: true, src: [
                        'resources/**',
                        '!resources/scss/**',
                        'src/**',
                        'vendor/**',
                        'libs/**',
                        '!vendor/jclabs/importwp/tests/**',
                        '!vendor/jclabs/importwp/vendor/**'], dest: 'build/'},
                    {expand: false, src: ['readme.txt'], dest: 'build/readme.txt'},
                    {expand: false, src: ['LICENSE.md'], dest: 'build/LICENSE.txt'},
                    {expand: false, src: ['jc-importer.php'], dest: 'build/jc-importer.php'},
                    {expand: false, src: ['uninstall.php'], dest: 'build/uninstall.php'},
                    {expand: false, src: ['assets/screenshot-1.png'], dest: 'build/screenshot-1.png'},
                    {expand: false, src: ['assets/screenshot-2.png'], dest: 'build/screenshot-2.png'},
                    {expand: false, src: ['assets/screenshot-3.png'], dest: 'build/screenshot-3.png'},
                    {expand: false, src: ['assets/screenshot-4.png'], dest: 'build/screenshot-4.png'}
                ]
            }
        },
        clean: {
            build: ["build/*", "!build/.svn/*"],
            tmp: ["build/app/tmp/*"],
            sass: ['resources/css/*']
        },
        phpunit: {
            classes: {
                dir: 'tests/'
            },
            options: {
                configuration: 'phpunit.xml',
                colors: true
            }
        },
        sass: {
            dev: {
                options: {
                    sourceMap: true,
                    outputStyle: 'nested'
                },
                files: {
                    'resources/css/style.css': 'resources/scss/init.scss'
                }
            },
            min: {
                options: {
                    sourceMap: false,
                    outputStyle: 'compressed'
                },
                files: {
                    'resources/css/style.min.css': 'resources/scss/init.scss'
                }
            }
        },
        watch: {
            sass: {
                files: 'resources/scss/**/*.scss',
                tasks: ['sass:dev']
            },
            js: {
                files: ['resources/js/**/*.js', '!resources/js/**/*.min.js'],
                tasks: ['uglify']
            }
        },
        'string-replace': {
            dist:{
                files:[{
                    expand: true,
                    cwd: './',
                    src: ['<%= pkg.name %>.php', 'readme.txt']
                }],
                options:{
                    replacements: [
                        {
                            pattern: /Version: ([0-9a-zA-Z\-\.]+)/m,
                            replacement: 'Version: <%= pkg.version %>'
                        },
                        {
                            pattern: /Stable tag: ([0-9a-zA-Z\-\.]+)/m,
                            replacement: 'Stable tag: <%= pkg.version %>'
                        },
                        {
                            pattern: /\$version = '([0-9a-zA-Z\-\.]+)';/m,
                            replacement: '$version = \'<%= pkg.version %>\';'
                        }
                    ]
                }
            }
        },
        wp_readme_to_markdown: {
            your_target: {
                files: {
                    'README.md': 'readme.txt'
                }
            }
        },
        uglify: {
            my_target: {
                files: {
                    'resources/js/importer.min.js': ['resources/js/importer.js'],
                    'resources/js/edit.min.js': ['resources/js/edit.js'],
                    'resources/js/vendor/jquery-tipTip.min.js': ['resources/js/vendor/jquery-tipTip.js']
                }
            }
        }

    });

    // grunt modules
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-phpunit');
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-string-replace');
    grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
    grunt.loadNpmTasks('grunt-contrib-uglify');

    // Default task(s).
    grunt.registerTask('default', ['sass', 'uglify','watch']);
    grunt.registerTask('build', ['string-replace', 'wp_readme_to_markdown', 'clean:sass', 'sass', 'uglify', 'clean:build', 'copy', "clean:tmp"]);

};