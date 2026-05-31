<?php
namespace Package\Raxon\Server\Trait;

use Entity\Extension;
use Exception;
use Package\Raxon\Account\Module\Permission;
use Raxon\App;
use Raxon\Config;
use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Doctrine\Module\Database;
use Raxon\Module\Dir;
use Raxon\Module\Event;
use Raxon\Module\File;
use Raxon\Node\Module\Node;
use Raxon\Node\Service\Security;
use Raxon\Parse\Module\Parse;

trait Setup {

    const CONNECTION = 'system';

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
            $extension_list = $this->extension_list_import_node($flags, $options);
            echo 'Imported ' . count($extension_list) . ' extension nodes' . PHP_EOL;
            $extension_list = $this->extension_list_import_sqlite($flags, $options);
            echo 'Imported ' . count($extension_list) . ' extensions in the db' . PHP_EOL;
            $content_type_list = $this->content_type_list_import_node($flags, $options);
            dd($content_type_list);
            echo 'Imported ' . count($content_type_list) . ' contentTypes' . PHP_EOL;
            foreach($extension_list as $nr => $extension){
                foreach($content_type_list as $content_type){
                    if($extension->getName() === $content_type->extension){
                        continue;
                    }
                    echo Cli::error('Extension ' . $extension . ' not found in the "content-type list", please add it manually.') . PHP_EOL;
                    echo Core::binary($object) . ' raxon/server content-type create -extension=' . $extension . ' -content_type=...'  .  PHP_EOL;
                }
            }
            foreach($content_type_list as $nr => $content_type){
                foreach($extension_list as $extension){
                    if($content_type->extension === $extension->getName()){
                        continue;
                    }
                    echo Cli::error('Extension ' . $extension . ' not found in the "extension list", please add it manually.') . PHP_EOL;
                    echo Core::binary($object) . ' raxon/server extension create -extension=' . $extension . ' -file_extension=... ' .  PHP_EOL;
                }
            }
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

    public function extension_list_import_sqlite(array|object $flags, array|object $options): array
    {
        $object = $this->object();
        $extension_list = $object->data_read($object->config('controller.dir.data') . 'System.Server.Extension' . $object->config('extension.json'));
        $options = Core::object($options, Core::OBJECT_ARRAY);
        if(!array_key_exists('connection', $options)){
            $options['connection'] = self::CONNECTION;
        }
        $node = new Node($object);
        $role = $node->role_system();
        $list = [];
        if($extension_list){
            foreach($extension_list->data('System.Server.Extension') as $extension => $file_extension){
                $entity = 'Extension';

                $config = Database::config($object);
                $connection = $object->config('doctrine.environment.system.*');
                $connection->manager = Database::entity_manager($object, $config, $connection);
                /*
                $entity = 'Task';
                $node = new Node($object);
                $role_system = $node->role_system();
                $task = new Task();
                $task->setUser($user->uuid);
                $task->setRequest($object->request());
                $task->setCommand([]);
                $task->setController([
                    $name
                ]);
                $task->setDescription('Speech to text task conversion from .wav generated in the browser and send to the backend to output a response.');
                $task->setStatus(Status::PENDING);
                $connection->manager->persist($task);
                $connection->manager->flush();
                */
                $repository = $connection->manager->getRepository($object->config('doctrine.entity.prefix') . $entity);

                $record = $repository->findOneBy([
                    'name' => $extension,
                ]);
                if(!$record){
                    $entity_extension = new Extension();
                    $entity_extension->setName($extension);
                    $connection->manager->persist($entity_extension);
                    $connection->manager->flush();
                    $record = $entity_extension;
                }
                $list[] = $record;
            }
        }
        return $list;
    }


    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function extension_list_import_node($flags, $options): array
    {
        $object = $this->object();
        $extension_list = $object->data_read($object->config('controller.dir.data') . 'System.Server.Extension' . $object->config('extension.json'));
        $list = [];
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
                $record = $record['node'] ?? null;
                if(!$record){
                    $record = (object) [
                        'extension' => $extension,
                        'file_extension' => $file_extension,
                    ];
                    $record = $node->create($class, $node->role_system(), $record);
                }
                elseif($record->file_extension !== $file_extension){
                    $record->file_extension = $file_extension;
                    $record = $node->patch($class, $node->role_system(), $record);
                } else {
                    //do nothing
                }
                $list[] = $record;
            }
        }
        return $list;
    }

    public function extension_list_create($flags, $options){

    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function content_type_list_import_node($flags, $options): array
    {
        $object = $this->object();
        $extension_list = $object->data_read($object->config('controller.dir.data') . 'System.Server.ContentType' . $object->config('extension.json'));
        $list = [];
        if($extension_list){
            $class = 'System.Server.ContentType';
            foreach($extension_list->data('System.Server.ContentType') as $extension => $content_type){
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
                $record = $record['node'] ?? null;
                if(!$record){
                    $record = (object) [
                        'extension' => $extension,
                        'content_type' => $content_type,
                    ];
                    $record = $node->create($class, $node->role_system(), $record);
                }
                elseif($record->content_type !== $content_type){
                    $record->content_type = $content_type;
                    $record = $node->patch($class, $node->role_system(), $record);
                } else {
                    //do nothing
                }
                $list[] = $record;
            }
        }
        return $list;
    }

    public function content_type_list_create($flags, $options){

    }
}