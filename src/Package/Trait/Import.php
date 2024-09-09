<?php
namespace Package\Raxon\Org\Server\Trait;

use Raxon\Org\App;

use Raxon\Org\Module\Core;
use Raxon\Org\Module\File;

use Raxon\Org\Node\Model\Node;

use Exception;
trait Import {

    public function role_system(): void
    {
        $object = $this->object();
        $package = $object->request('package');
        if($package){
            $node = new Node($object);
            $node->role_system_create($package);
        }
    }
}