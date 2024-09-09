{{R3M}}
{{$register = Package.Raxon.Server:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Server:Import:role.system()}}
{{$options = options()}}
{{if(is.empty($options.public))}}
{{$options.public = config('server.public')}}
{{/if}}
{{Package.Raxon.Server:Main:public.create($options)}}
{{/if}}