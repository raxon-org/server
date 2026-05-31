<?php
namespace Package\Raxon\Server\Trait;

use Exception;
use Raxon\App;
use Raxon\Config;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\Event;
use Raxon\Module\File;
use Raxon\Node\Module\Node;
use Raxon\Node\Service\Security;
use Raxon\Parse\Module\Parse;

trait Setup {

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function public_create($flags, $options): ?string
    {
        $object = $this->object();
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $id = $object->config(Config::POSIX_ID);
        if(
            !in_array(
                $id,
                [
                    0,
                    33
                ],
                true
            )
        ){
            $exception = new Exception('Only root and after that www-data can configure public create...');
            Event::trigger($object, 'raxon.org.server.public.create', [
                'options' => $options,
                'exception' => $exception
            ]);
            throw $exception;
        }
        $node = new Node($object);
        $class = 'System.Server';
        if (!array_key_exists('function', $options)) {
            $options['function'] = __FUNCTION__;
        }
        $options['relation'] = false;
        if (!Security::is_granted(
            $class,
            $node->role_system(),
            $options
        )) {
            return false;
        }
        if(
            !array_key_exists('public', $options) ||
            empty($options['public'])
        ){
            $options['public'] = $object->config('project.dir.public');
        }
        if(strstr($options['public'], '/') === false){
            $options['public'] = $object->config('project.dir.root') . $options['public'] . $object->config('ds');
        }
        $destination = $options['public'];
        Dir::create($destination, Dir::CHMOD);
        $source = $object->config('controller.dir.data') . '.htaccess';
        $destination = $options['public'] . '.htaccess';
        File::copy($source, $destination);
        $source = $object->config('controller.dir.data') . '.user.ini';
        $destination = $options['public'] . '.user.ini';
        File::copy($source, $destination);
        $data = new Data($object->data());
        $flags = App::flags($object);
        $parse_options = (object) [
            'source' => $destination
        ];
        $parse = new Parse($object, $data, $flags, $parse_options);
        $read = File::read($destination);
        $read = $parse->compile($read, $data);
        File::write($destination, $read);
        $source = $object->config('controller.dir.data') . 'index.php';
        $destination = $options['public'] . 'index.php';
        File::copy($source, $destination);
        File::permission($object, [
            'public' => $options['public'],
            '.htaccess' => $options['public'] . '.htaccess',
            '.user.ini' => $options['public'] . '.user.ini',
            'index.php' => $options['public'] . 'index.php',
        ]);
        $response = $node->record($class, $node->role_system());
        if(!$response){
            $record = (object) [
                'public' => $options['public'],
                '#class' => $class
            ];
            $response = $node->create($class, $node->role_system(), $record);
            $config = $this->system_config($node);
            if(
                $config &&
                is_array($config) &&
                array_key_exists('node', $config) &&
                is_object($config['node']) &&
                property_exists($config['node'], 'server') &&
                !empty($config['node']->server) &&
                $response &&
                is_array($response) &&
                array_key_exists('node', $response) &&
                is_object($response['node']) &&
                property_exists($response['node'], 'public') &&
                !empty($response['node']->public) &&
                Dir::is($response['node']->public)
            ){
                echo 'Server public directory (' . $response['node']->public .') configured (create)' . PHP_EOL;
                Event::trigger($object, 'raxon.org.server.public.create', [
                    'options' => $options,
                    'response' => $response
                ]);
                return null;
            }
        }
        elseif(
            is_array($response) &&
            array_key_exists('node', $response) &&
            is_object($response['node']) &&
            property_exists($response['node'], 'uuid')
        ){
            $config = $this->system_config($node);
            //dont forget to update the insert
            $record = (object) [
                'uuid' => $response['node']->uuid,
                'public' => $options['public'],
                'extension' => '*',
                'contentType' => '*',
                '#class' => $class
            ];
            if(
                property_exists($response['node'], 'public') &&
                !empty($response['node']->public) &&
                Dir::is($response['node']->public) &&
                $record->public !== $response['node']->public
            ){
                Dir::remove($response['node']->public);
            }
            $response = $node->patch($class, $node->role_system(), $record);
            //create extension list
            //create content type list

            $extension_list = $object->data_read($object->config('controller.dir.data') . 'System.Server.Extension' . $object->config('extension.json'));
            if($extension_list){
                $class = 'System.Server.Extension';
                foreach($extension_list->data('System.Server.Extension') as $extension => $file_extension){
                    $node = new Node($object);
                    $record = $node->record($class, $node->role_system(), [
                        'where' => [
                            [
                                'attribute' => 'extension',
                                'operator' => '===',
                                'value' => $extension,
                            ]
                        ]
                    ]);
                    if(!$record){
                        $create = (object) [
                            'extension' => $extension,
                            'file_extension' => $file_extension,
                        ];
                        $create = $node->create($class, $node->role_system(), $create);
                        d($create);
                    }
                    d($record);
                    d($extension);
                    dd($file_extension);
                }
            }
            ddd($extension_list);



            if(
                $config &&
                is_array($config) &&
                array_key_exists('node', $config) &&
                is_object($config['node']) &&
                property_exists($config['node'], 'server') &&
                !empty($config['node']->server) &&
                $response &&
                is_array($response) &&
                array_key_exists('node', $response) &&
                is_object($response['node']) &&
                property_exists($response['node'], 'public') &&
                !empty($response['node']->public) &&
                Dir::is($response['node']->public)
            ){
                echo 'Server public directory (' . $response['node']->public .') configured (patch)' . PHP_EOL;
                Event::trigger($object, 'raxon.org.server.public.create', [
                    'options' => $options,
                    'response' => $response
                ]);
                return null;
            }
            if(
                $response &&
                is_array($response) &&
                array_key_exists('error', $response)
            ){
                $result = Core::object($response, Core::OBJECT_JSON) . PHP_EOL;
                Event::trigger($object, 'raxon.org.server.public.create', [
                    'options' => $options,
                    'response' => $response
                ]);
                return $result;
            }
        }
        $exception = new Exception('Server public directory (' . $options['public'] .') not configured...');
        Event::trigger($object, 'raxon.org.server.public.create', [
            'options' => $options,
            'exception' => $exception
        ]);
        throw $exception;
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function restore($options): ?string
    {
        $object = $this->object();
        $options = Core::object($options, Core::OBJECT_ARRAY);
        $id = $object->config(Config::POSIX_ID);
        if(
            !in_array(
                $id,
                [
                    0,
                    33
                ],
                true
            )
        ){
            $exception = new Exception('Only root and after that www-data can restore...');
            Event::trigger($object, 'raxon.org.server.reset', [
                'options' => $options,
                'exception' => $exception
            ]);
            throw $exception;
        }
        $node = new Node($object);
        $class = 'System.Server';
        if (!array_key_exists('function', $options)) {
            $options['function'] = __FUNCTION__;
        }
        $options['relation'] = false;
        if (!Security::is_granted(
            $class,
            $node->role_system(),
            $options
        )) {
            return false;
        }
        if(
            !array_key_exists('public', $options) ||
            empty($options['public'])
        ){
            $options['public'] = $object->config('project.dir.public');
        }
        if(strstr($options['public'], '/') === false){
            $options['public'] = $object->config('project.dir.root') . $options['public'] . $object->config('ds');
        }
        $destination = $options['public'];
        if(!Dir::exist($destination)){
            Dir::create($destination, Dir::CHMOD);
            File::permission($object, [
                'destination' => $destination,
            ]);
        }
        $source = $object->config('controller.dir.data') . '.htaccess';
        $destination = $options['public'] . '.htaccess';
        if(!File::exist($destination)){
            File::copy($source, $destination);
            File::permission($object, [
                'destination' => $destination,
            ]);
        }
        $source = $object->config('controller.dir.data') . '.user.ini';
        $destination = $options['public'] . '.user.ini';
        if(!File::exist($destination)) {
            File::copy($source, $destination);
            $data = new Data($object->data());
            $flags = App::flags($object);
            $parse_options = (object) [
                'source' => $destination
            ];
            $parse = new Parse($object, $data, $flags, $parse_options);
            $read = File::read($destination);
            $read = $parse->compile($read, $data);
            File::write($destination, $read);
            File::permission($object, [
                'destination' => $destination,
            ]);
        }
        $source = $object->config('controller.dir.data') . 'index.php';
        $destination = $options['public'] . 'index.php';
        if(!File::exist($destination)){
            File::copy($source, $destination);
            File::permission($object, [
                'destination' => $destination,
            ]);
        }
        $response = $node->record($class, $node->role_system());
        if(!$response){
            $record = (object) [
                'public' => $options['public'],
                '#class' => $class
            ];
            $response = $node->create($class, $node->role_system(), $record);
            $config = $this->system_config($node);
            if(
                $config &&
                is_array($config) &&
                array_key_exists('node', $config) &&
                is_object($config['node']) &&
                property_exists($config['node'], 'server') &&
                !empty($config['node']->server) &&
                $response &&
                is_array($response) &&
                array_key_exists('node', $response) &&
                is_object($response['node']) &&
                property_exists($response['node'], 'public') &&
                !empty($response['node']->public) &&
                Dir::is($response['node']->public)
            ){
                echo 'Server public directory (' . $response['node']->public .') configured (create)' . PHP_EOL;
                Event::trigger($object, 'raxon.org.server.reset', [
                    'options' => $options,
                    'response' => $response
                ]);
                return null;
            }
        }
        elseif(
            is_array($response) &&
            array_key_exists('node', $response) &&
            is_object($response['node']) &&
            property_exists($response['node'], 'uuid')
        ){
            $config = $this->system_config($node);
            $record = (object) [
                'uuid' => $response['node']->uuid,
                'public' => $options['public'],
                '#class' => $class
            ];
            if(
                property_exists($response['node'], 'public') &&
                !empty($response['node']->public) &&
                Dir::is($response['node']->public) &&
                $record->public !== $response['node']->public
            ){
                Dir::remove($response['node']->public);
            }
            $response = $node->patch($class, $node->role_system(), $record);
            if(
                $config &&
                is_array($config) &&
                array_key_exists('node', $config) &&
                is_object($config['node']) &&
                property_exists($config['node'], 'server') &&
                !empty($config['node']->server) &&
                $response &&
                is_array($response) &&
                array_key_exists('node', $response) &&
                is_object($response['node']) &&
                property_exists($response['node'], 'public') &&
                !empty($response['node']->public) &&
                Dir::is($response['node']->public)
            ){
                echo 'Server public directory (' . $response['node']->public .') configured (patch)' . PHP_EOL;
                Event::trigger($object, 'raxon.org.server.reset', [
                    'options' => $options,
                    'response' => $response
                ]);
                return null;
            }
            if(
                $response &&
                is_array($response) &&
                array_key_exists('error', $response)
            ){
                $result = Core::object($response, Core::OBJECT_JSON) . PHP_EOL;
                Event::trigger($object, 'raxon.org.server.reset', [
                    'options' => $options,
                    'response' => $response
                ]);
                return $result;
            }
        }
        $exception = new Exception('Server public directory (' . $options['public'] .') not configured...');
        Event::trigger($object, 'raxon.org.server.reset', [
            'options' => $options,
            'exception' => $exception
        ]);
        throw $exception;
    }

    public function system_config($node): ?array
    {
        $config = $node->record('System.Config', $node->role_system());
        if(
            $config &&
            is_array($config) &&
            array_key_exists('node', $config) &&
            property_exists($config['node'], 'uuid') &&
            !property_exists($config['node'], 'server')
        ){
            $patch = (object) [
                'uuid' => $config['node']->uuid,
                'server' => '*' //we have $response and can use the uuid too.
            ];
            $config = $node->patch('System.Config', $node->role_system(), $patch);
        }
        return $config;
    }

    public function extension_list_create($flags, $options){

    }

    public function content_type_list_create($flags, $options){

    }
}