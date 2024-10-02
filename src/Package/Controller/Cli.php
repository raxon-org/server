<?php

namespace Package\Raxon\Server\Controller;

use Raxon\App;
use Raxon\Config;

use Raxon\Module\Core;
use Raxon\Module\Controller;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Exception;

use Raxon\Exception\LocateException;
use Raxon\Exception\ObjectException;
use Raxon\Exception\UrlEmptyException;
use Raxon\Exception\UrlNotExistException;

class Cli extends Controller {
    const DIR = __DIR__ . '/';
    const MODULE_INFO = 'Info';
    const INFO = [
        '{{binary()}} raxon/server                  | Server options',
        '{{binary()}} raxon/server setup            | Server setup',
    ];

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function run(App $object): mixed
    {
        $node = $object->request(0);
        $scan = Cli::scan($object);
        $module = (string) $object->parameter($object, $node, 1);
        if(
            !in_array(
                $module,
                $scan['module'],
                true
            )
        ){
            $module = Cli::MODULE_INFO;
        }
        $submodule = (string) $object->parameter($object, $node, 2);
        if(
            !in_array(
                $submodule,
                $scan['submodule'],
                true
            )
        ){
            $submodule = false;
        }
        $command = (string) $object->parameter($object, $node, 3);
        if(
            !in_array(
                $command,
                $scan['command'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO
        ){
            $command = false;
        }
        $subcommand = (string) $object->parameter($object, $node, 4);
        if(
            !in_array(
                $subcommand,
                $scan['subcommand'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO ||
            $command === Cli::MODULE_INFO
        ){
            $subcommand = false;
        }
        $action = (string) $object->parameter($object, $node, 5);
        if(
            !in_array(
                $action,
                $scan['action'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO ||
            $command === Cli::MODULE_INFO ||
            $subcommand === Cli::MODULE_INFO
        ){
            $action = false;
        }
        $subaction = (string) $object->parameter($object, $node, 6);
        if(
            !in_array(
                $subaction,
                $scan['subaction'],
                true
            ) ||
            $module === Cli::MODULE_INFO ||
            $submodule === Cli::MODULE_INFO ||
            $command === Cli::MODULE_INFO ||
            $subcommand === Cli::MODULE_INFO ||
            $action === Cli::MODULE_INFO
        ){
            $subaction = false;
        }
        try {
            if(
                !empty($submodule) &&
                !empty($command) &&
                !empty($subcommand) &&
                !empty($action) &&
                !empty($subaction)
            ){
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command) .
                    '.' .
                    ucfirst($subcommand) .
                    '.' .
                    ucfirst($action) .
                    '.' .
                    ucfirst($subaction)
                );
            }
            elseif(
                !empty($submodule) &&
                !empty($command) &&
                !empty($subcommand) &&
                !empty($action)
            ){
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command) .
                    '.' .
                    ucfirst($subcommand) .
                    '.' .
                    ucfirst($action)
                );
            }
            elseif(
                !empty($submodule) &&
                !empty($command) &&
                !empty($subcommand)
            ){
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command) .
                    '.' .
                    ucfirst($subcommand)
                );
            }
            elseif(
                !empty($submodule) &&
                !empty($command)
            ){
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule) .
                    '.' .
                    ucfirst($command)
                );
            }
            elseif(!empty($submodule)){
                $url = Cli::locate(
                    $object,
                    ucfirst($module) .
                    '.' .
                    ucfirst($submodule)
                );
            } else {
                $url = Cli::locate(
                    $object,
                    ucfirst($module)
                );
            }
            $object->request('package', $node);
            $object->request('module', $module);
            if($submodule){
                $object->request('submodule', $submodule);
            }
            if($command){
                $object->request('command', $command);
            }
            if($subcommand){
                $object->request('subcommand', $subcommand);
            }
            if($action){
                $object->request('action', $action);
            }
            if($subaction){
                $object->request('subaction', $subaction);
            }
            return Cli::response($object, $url);
        } catch (Exception | UrlEmptyException | UrlNotExistException | LocateException $exception){
            return $exception;
        }
    }

    /**
     * @throws Exception
     */
    private static function scan(App $object): array
    {
        $scan = [
            'module' => [],
            'submodule' => [],
            'command' => [],
            'subcommand' => [],
            'action' => [],
            'subaction' => []
        ];
        $url = $object->config('controller.dir.view');
        if(!Dir::exist($url)){
            return $scan;
        }
         $dir = new Dir();
        $read = $dir->read($url, true);
        if(!$read){
            return $scan;
        }
        foreach($read as $nr => $file){
            if($file->type !== File::TYPE){
                continue;
            }
            $part = substr($file->url, strlen($url));
            $explode = explode('/', $part, 6);
            if(array_key_exists(0, $explode) === false){
                continue;
            }
            $module = mb_strtolower(File::basename($explode[0], $object->config('extension.tpl')));
            $submodule = false;
            $temp = explode('.', $module, 2);
            if(array_key_exists(1, $temp)){
                $module = $temp[0];
                $submodule = $temp[1];
            } else {
                if(array_key_exists(1, $explode)){
                    $submodule = mb_strtolower(File::basename($explode[1], $object->config('extension.tpl')));
                    $temp = explode('.', $submodule, 2);
                    if(array_key_exists(1, $temp)){
                        $submodule = $temp[0];
                        $command = $temp[1];
                    }
                }
            }
            $command = false;
            $subcommand = false;
            $action = false;
            $subaction = false;
            if(array_key_exists(2, $explode)){
                $command = mb_strtolower(File::basename($explode[2], $object->config('extension.tpl')));
                $temp = explode('.', $command, 2);
                if(array_key_exists(1, $temp)){
                    $command = $temp[0];
                    $subcommand = $temp[1];
                }
            }
            if(array_key_exists(3, $explode) && $subcommand === false){
                $subcommand = mb_strtolower(File::basename($explode[3], $object->config('extension.tpl')));
                $temp = explode('.', $subcommand, 2);
                if(array_key_exists(1, $temp)){
                    $subcommand = $temp[0];
                    $action = $temp[1];
                }
            }
            if(array_key_exists(4, $explode) && $action === false){
                $action = mb_strtolower(File::basename($explode[4], $object->config('extension.tpl')));
                $temp = explode('.', $action, 2);
                if(array_key_exists(1, $temp)){
                    $action = $temp[0];
                    $subaction = $temp[1];
                }
            }
            if(array_key_exists(5, $explode) && $subaction === false){
                $subaction = mb_strtolower(File::basename($explode[5], $object->config('extension.tpl')));
            }
            if(
                !in_array(
                    $module,
                    $scan['module'],
                    true
                )
            ){
                $scan['module'][] = $module;
            }
            if(
                $submodule &&
                !in_array(
                    $submodule,
                    $scan['submodule'],
                    true
                )
            ){
                $scan['submodule'][] = $submodule;
            }
            if(
                $command  &&
                !in_array(
                    $command,
                    $scan['command'],
                    true
                )
            ){
                $scan['command'][] = $command;
            }
            if(
                $subcommand &&
                !in_array(
                    $subcommand,
                    $scan['subcommand'],
                    true
                )
            ){
                $scan['subcommand'][] = $subcommand;
            }
            if(
                $action &&
                !in_array(
                    $action,
                    $scan['action'],
                    true
                )
            ){
                $scan['action'][] = $subcommand;
            }
            if(
                $subaction &&
                !in_array(
                    $subaction,
                    $scan['subaction'],
                    true
                )
            ){
                $scan['subaction'][] = $subcommand;
            }
        }
        return $scan;
    }
}