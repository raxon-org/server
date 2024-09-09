{{R3M}}
{{$register = Package.Raxon.Org.Server:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Org.Server:Import:role.system()}}
{{$options = options()}}
{{if(is.empty($options.public))}}
{{$options.public = config('server.public')}}
{{/if}}
{{Package.Raxon.Org.Server:Main:public.create($options)}}
{{/if}}