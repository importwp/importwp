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
            deploy: {
                options: {
                    sourceMap: false,
                    outputStyle: 'compressed'
                },
                files: {
                    'app/assets/css/style.css': 'app/assets/scss/init.scss'
                }
            }
        },
        watch: {
            sass: {
                files: 'app/assets/scss/**/*.scss',
                tasks: ['sass:dev']
            }
        }
    });

    // grunt modules
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-phpunit');
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-contrib-watch'); 

    // Default task(s).
    grunt.registerTask('default', ['phpunit', 'clean:sass', 'sass:deploy', 'clean:build', 'copy', "clean:tmp"]);
    grunt.registerTask('build', ['clean:sass', 'sass:deploy', 'clean:build', 'copy', "clean:tmp"]);

};