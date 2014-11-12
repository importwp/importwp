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
                    {expand: false, src: ['jc-importer.php'], dest: 'build/jc-importer.php'}
                ]
            }
        },
        clean: ["build/*", "!build/.svn/*"],
    });

    // grunt modules
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');

    // Default task(s).
    grunt.registerTask('default', ['clean', 'copy']);

};