module.exports = function(grunt) {

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        copy: {
            main: {
                files: [
                    {expand: true, src: ['app/**'], dest: 'build/'},
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
            tmp: ["build/app/tmp/*"]
        },
        phpunit: {
            classes: {
                dir: 'tests/'
            },
            options: {
                configuration: 'phpunit.xml',
                colors: true
            }
        }
    });

    // grunt modules
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-phpunit');

    // Default task(s).
    grunt.registerTask('default', ['phpunit', 'clean:build', 'copy', "clean:tmp"]);

};