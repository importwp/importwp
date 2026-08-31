<?php

namespace ImportWPTests\Common\Filesystem;

use ImportWP\Common\Filesystem\Filesystem;

class ImporterFilePathTest extends \WP_UnitTestCase
{
    public function test_to_uploads_relative_path()
    {
        $basedir = wp_normalize_path(untrailingslashit(wp_upload_dir()['basedir']));
        $absolute = $basedir . '/importwp/uploads/example.xml';

        $this->assertEquals(
            'importwp/uploads/example.xml',
            Filesystem::to_uploads_relative_path($absolute)
        );
    }

    public function test_resolve_relative_importer_file_path()
    {
        $basedir = wp_normalize_path(untrailingslashit(wp_upload_dir()['basedir']));
        $dir = $basedir . '/importwp/uploads';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $file = $dir . '/resolve-relative-test.xml';
        file_put_contents($file, '<root/>');

        $resolved = Filesystem::resolve_importer_file_path('importwp/uploads/resolve-relative-test.xml');
        $this->assertEquals(wp_normalize_path($file), $resolved);

        unlink($file);
    }

    public function test_resolve_legacy_absolute_path_outside_basedir_without_warning()
    {
        $basedir = wp_normalize_path(untrailingslashit(wp_upload_dir()['basedir']));
        $dir = $basedir . '/importwp/uploads';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $file = $dir . '/legacy-absolute-test.xml';
        file_put_contents($file, '<root/>');

        // Simulate a stored absolute path from an old hosting layout.
        $legacy = '/sites/example.com/wp-content/uploads/importwp/uploads/legacy-absolute-test.xml';
        $resolved = Filesystem::resolve_importer_file_path($legacy);

        $this->assertEquals(wp_normalize_path($file), $resolved);

        unlink($file);
    }

    public function test_resolve_missing_legacy_path_returns_false()
    {
        $legacy = '/sites/example.com/wp-content/uploads/importwp/uploads/does-not-exist.xml';
        $this->assertFalse(Filesystem::resolve_importer_file_path($legacy));
    }
}
