module.exports = function(grunt) {

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        copy: {
            main: {
                files: [
                    {expand: true, src: ['app/**', '!app/assets/scss/**'], dest: 'build/'},
                    {expand: false, src: ['readme.txt'], dest: 'build/readme.txt'},
                    {expand: false, src: ['LICENSE.md'], dest: 'build/LICENSE.txt'},
                    {expand: false, src: ['jc-importer.php'], dest: 'build/jc-importer.php'},
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
            sass: ['app/assets/css/*']
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
                    'app/assets/css/style.css': 'app/assets/scss/init.scss'
                }
            },
            min: {
                options: {
                    sourceMap: false,
                    outputStyle: 'compressed'
                },
                files: {
                    'app/assets/css/style.min.css': 'app/assets/scss/init.scss'
                }
            }
        },
        watch: {
            sass: {
                files: 'app/assets/scss/**/*.scss',
                tasks: ['sass:dev']
            },
            js: {
                files: 'app/assets/js/**/*.js',
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
                    'app/assets/js/importer.min.js': ['app/assets/js/importer.js'],
                    'app/assets/js/main.min.js': ['app/assets/js/main.js'],
                    'app/assets/js/jquery-tipTip.min.js': ['app/assets/js/jquery-tipTip.js']
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