<?php
namespace Raxon\Output\Filter\System;

use Raxon\App;

use Raxon\Module\Controller;

class Server extends Controller {
    const DIR = __DIR__ . '/';

    public static function url(App $object, $response=null): object
    {
        $result = [];
        $count = 0;
        if(
            !empty($response) &&
            is_array($response) ||
            is_object($response)
        ){
            foreach($response as $nr => $record){
                if(
                    is_array($record) &&
                    array_key_exists('name', $record) &&
                    array_key_exists('options', $record)
                ){
                    $name = str_replace('.', '-', strtolower($record['name']));
                    $result[$name] = $record['options'];
                    $count++;
                }
                elseif(
                    is_object($record) &&
                    property_exists($record, 'name') &&
                    property_exists($record, 'options')
                ){
                    $name = str_replace('.', '-', strtolower($record->name));
                    $result[strtolower($name)] = $record->options;
                    $count++;
                }
            }
        }
        if($count > 0){
            return (object) $result;
        }
        return $response;

    }

    public static function output_filter(App $object, mixed $response=null): array|object
    {
        foreach($response as $nr => $record){
            if(property_exists($record, 'extension')){
                $extension_list = [];
                $is_extension_list = false;
                foreach($record->extension as $extension){
                    if(
                        property_exists($extension, '#class') &&
                        property_exists($extension, 'uuid') &&
                        property_exists($extension, 'extension') &&
                        property_exists($extension, 'file_extension')
                    ){
                        if(!in_array($extension->extension, $extension_list, true)){
                            $extension_list[$extension->extension] = $extension->file_extension;
                            $is_extension_list = true;
                        }
                    }
                }
                if($is_extension_list){
                    $record->extension = $extension_list;
                }
            }
            if(property_exists($record, 'contentType')){
                $content_type_list = [];
                $is_content_type_list = false;
                foreach($record->contentType as $contentType){
                    if(
                        property_exists($contentType, '#class') &&
                        property_exists($contentType, 'uuid') &&
                        property_exists($contentType, 'extension') &&
                        property_exists($contentType, 'content_type')
                    ){
                        if(!in_array($contentType->extension, $content_type_list, true)){
                            $content_type_list[$extension->extension] = $contentType->content_type;
                            $is_content_type_list = true;
                        }
                    }
                }
                if($is_content_type_list){
                    $record->contentType = $content_type_list;
                }
            }
        }
        return $response;
    }

    public static function extension(App $object, $response=null): object
    {
        dd($response);
        return $response;
    }

    public static function contentType(App $object, $response=null): object
    {
        dd($response);
        return $response;
    }
}