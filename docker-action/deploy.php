<?php

namespace Deployer;

require 'recipe/common.php';
use Deployer\Exception\Exception;
use Deployer\Exception\RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;

const DB_UPDATE_NEEDED_EXIT_CODE = 2;


set('database_upgrade_needed', function () {
    try {
        run('{{bin/php}} {{release_path}}/{{magento_bin}} setup:db:status');
    } catch (ProcessFailedException $e) {
        if ($e->getProcess()->getExitCode() == DB_UPDATE_NEEDED_EXIT_CODE) {
            return true;
        }
        throw $e;
    } catch (RuntimeException $e) {
        if ($e->getExitCode() == DB_UPDATE_NEEDED_EXIT_CODE) {
            return true;
        }
        throw $e;
    }
    return false;
});

task('database:upgrade', function () {
    if (get('database_upgrade_needed')) {
        run('{{bin/php}} {{release_path}}/{{magento_bin}} setup:db-schema:upgrade --no-interaction');
        run('{{bin/php}} {{release_path}}/{{magento_bin}} setup:db-data:upgrade --no-interaction');
    } else {
        writeln('Skipped -> All Modules are up to date');
    }
});

task('maintenance:set:if-needed', function () {
    get('database_upgrade_needed') || get('config_import_needed') ?
        invoke('maintenance:set') :
        writeln('Skipped -> Maintenance is not needed');
});
const OUTPUT_CONFIG_IMPORT_NEEDED = 'This command is unavailable right now. ' .
    'To continue working with it please run app:config:import or setup:upgrade command before.';

set('config_import_needed', function () {
    try {
        // NOTE: Workaround until "app:config:status" is available on Magento 2.2.3
        run('{{bin/php}} {{release_path}}/{{magento_bin}} config:set workaround/check/config_status 1');
    } catch (ProcessFailedException $e) {
        if (trim($e->getProcess()->getOutput()) == OUTPUT_CONFIG_IMPORT_NEEDED) {
            return true;
        }
    } catch (RuntimeException $e) {
        if (trim($e->getOutput()) == OUTPUT_CONFIG_IMPORT_NEEDED) {
            return true;
        }
    }
    return false;
});

set('magento_bin', 'bin/magento');



desc('Creating Override symlinks for override shared files and dirs');
task('deploy:override_shared', function () {
    $sharedPath = "{{deploy_path}}/shared";

    // Validate shared_dir, find duplicates
    foreach (get('override_shared_dirs') as $a) {
        foreach (get('override_shared_dirs') as $b) {
            if ($a !== $b && strpos(rtrim($a, '/') . '/', rtrim($b, '/') . '/') === 0) {
                throw new Exception("Can not share same dirs `$a` and `$b`.");
            }
        }
    }

    foreach (get('override_shared_dirs') as $dir) {
        // Check if shared dir exists.
        if (test("[ -d $sharedPath/$dir ]")) {
            // remove shared dir
            run("rm -rf $sharedPath/$dir");
        }
        // If release contains shared dir, copy that dir from release to shared.
        if (test("[ -d $(echo {{release_path}}/$dir) ]")) {
            run("cp -rv {{release_path}}/$dir $sharedPath/" . dirname(parse($dir)));
            run("rm -rf {{release_path}}/$dir");
        } else {
            // Create shared dir if it does not exist.
            run("mkdir -p $sharedPath/$dir");
        }

        // Create path to shared dir in release dir if it does not exist.
        // Symlink will not create the path and will fail otherwise.
        run("mkdir -p `dirname {{release_path}}/$dir`");

        // Symlink shared dir to release dir
        run("{{bin/symlink}} $sharedPath/$dir {{release_path}}/$dir");
    }

    foreach (get('override_shared_files') as $file) {
        $dirname = dirname(parse($file));

        // Create dir of shared file
        run("mkdir -p $sharedPath/" . $dirname);

        // Check if shared file exists in shared and remove it
        if (test("[ -f $sharedPath/$file ]")) {
            run("rm -rf $sharedPath/$file");
        }

        // If file exist in release
        if (test("[ -f {{release_path}}/$file ]")) {
            // Copy file in shared dir if not present
            run("cp -rv {{release_path}}/$file $sharedPath/$file");
            run("rm -rf {{release_path}}/$file");
        }

        // Ensure dir is available in release
        run("if [ ! -d $(echo {{release_path}}/$dirname) ]; then mkdir -p {{release_path}}/$dirname;fi");

        // Touch shared
        run("touch $sharedPath/$file");

        // Symlink shared dir to release dir
        run("{{bin/symlink}} $sharedPath/$file {{release_path}}/$file");
    }
});
task('maintenance:set', function () {
    # IMPORTANT: do not use {{current_path}} for the "-f" check.
    # {{current_path}} returns error if symlink does not exists
    test('[ -f {{deploy_path}}/current/{{magento_bin}} ]') ?
        run('{{bin/php}} {{current_path}}/{{magento_bin}} maintenance:enable') :
        writeln('Skipped -> current not found');
});

task('maintenance:unset', function () {
    # IMPORTANT: do not use {{current_path}} for the "-f" check.
    # {{current_path}} returns error if symlink does not exists
    if (!test('[ -f {{deploy_path}}/current/{{magento_bin}} ]')) {
        writeln('Skipped -> current not found');
        return;
    }
    test('[ -f {{deploy_path}}/current/{{magento_dir}}/var/.maintenance.flag ]') ?
        run('{{bin/php}} {{current_path}}/{{magento_bin}} maintenance:disable') :
        writeln('Skipped -> maintenance is already unset');
});

task('artifact:package', 'mkdir {{artifact_path}} && tar --exclude=\'artifacts*\' --exclude=\'deploy.php\' -czf {{artifact_path}}/{{artifact_file}} .');

task('artifact:upload', function () {
    upload(get('artifact_path'), '{{release_path}}');
});

task ('composer:lock:get', function () {
    download("{{current_path}}/composer.lock", 'old_composer.lock');
});

task('artifact:extract', '
	tar -xzpf {{release_path}}/{{artifact_path}}/{{artifact_file}} -C {{release_path}};
	rm -rf {{release_path}}/{{artifact_path}}
');


task('cache:clear:magento', '{{bin/php}} {{magento_bin}} cache:flush');

task('cache:clear', function () {
    invoke('cache:clear:magento');
});

task('cache:enable', function () {
    $enabledCaches = get('cache_enabled_caches');

    if (empty($enabledCaches)) {
        return;
    }

    $command = '{{bin/php}} {{release_path}}/{{magento_bin}} cache:enable';

    if ($enabledCaches === 'all') {
        run($command);
    }

    if (is_array($enabledCaches)) {
        run($command . ' ' . implode(' ', $enabledCaches));
    }
});

desc('Magento setup: upgrade');
task('magento:setup:upgrade', function () {
    run("{{bin/php}} {{release_path}}/{{magento_bin}} setup:upgrade");
});

task('files:compile', '{{bin/php}} {{magento_bin}} setup:di:compile');
task('files:optimize-autoloader', '{{bin/composer}} dump-autoload --optimize --apcu');
task('files:static_assets', '{{bin/php}} {{magento_bin}} setup:static-content:deploy {{languages}} {{static_deploy_options}}');

task('cache:clear:if-maintenance', function () {
    test('[ -f {{deploy_path}}/current/{{magento_dir}}/var/.maintenance.flag ]') ?
        invoke('cache:clear:magento') :
        writeln('Skipped -> maintenance is not set');
});

desc('Build Artifact');
task('build', function () {
    set('deploy_path', '.');
    set('release_path', '.');
    set('current_path', '.');
    $origStaticOptions = get('static_deploy_options');
    set('static_deploy_options', '-f ' . $origStaticOptions);

    invoke('deploy:vendors');
    invoke('artifact:package');
})->local();
localhost('build');


desc('Deploy artifact');
task('deploy-artifact', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'artifact:upload',
    'artifact:extract',
    //'deploy:clear_paths',
    'deploy:shared',
    'maintenance:set:if-needed',
    'database:upgrade',
    'magento:setup:upgrade',
    'files:compile',
    //'files:optimize-autoloader',
    'files:static_assets',
    'cache:clear:if-maintenance',
    //'deploy:override_shared',
    'deploy:symlink',
    'maintenance:unset',
    'cache:clear',
    'cache:enable',
    'deploy:unlock',
    'cleanup',
    'success',
]);
fail('deploy-artifact', 'deploy:failed');
after('deploy:failed', 'deploy:unlock');

task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:shared',
    'maintenance:set:if-needed',
    'magento:setup:upgrade',
    'files:compile',
    'files:optimize-autoloader',
    'files:static_assets',
    'cache:clear:if-maintenance',
    //'deploy:override_shared',
    'deploy:symlink',
    'maintenance:unset',
    'cache:clear',
    'cache:enable',
    'deploy:unlock',
    'cleanup',
    'success',
]);

// Use timestamp for release name
set('release_name', function () {
    return date('YmdHis');
});

//Deploy configuration

set('static_deploy_options', '--jobs=4 -f');
set('magento_dir', '.');
set('repository', '');
set('languages', 'en_US nl_NL');
set('cache_enabled_caches', 'all');
set('artifact_path', 'artifacts');
set('artifact_file', 'artifact.tar.gz');
set('default_timeout', 1200);
set('artifact_excludes_file',['deploy.php','artifacts']);


set('shared_files', [
    '{{magento_dir}}/app/etc/env.php',
    '{{magento_dir}}/app/etc/config.php',
]);
set('shared_dirs', [
    '{{magento_dir}}/pub/media',
    '{{magento_dir}}/var/log',
    '{{magento_dir}}/var/report',
    '{{magento_dir}}/var/export',
    '{{magento_dir}}/var/session'
]);

inventory('hosts.yml');
