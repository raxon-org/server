{{$register = Package.Raxon.Server:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Server:Import:role.system()}}
{{$options = options()}}
{{if(is.empty($options.public))}}
{{$options.public = config('server.public')}}
{{/if}}
{{$flags = flags()}}
{{Package.Raxon.Server:Setup:public.create($flags, $options)}}
{{Package.Raxon.Server:Setup:extension.list.create($flags, $options)}}
{{Package.Raxon.Server:Setup:content.type.list.create($flags, $options)}}
{{/if}}