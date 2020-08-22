
<div id="rows-list" class="panel">
    <form action="{$url}" class="form-horizontal" method="post">
        <div class="panel-heading">
            <i class="icon-file-text"></i> Ustawienia</span>
        </div>
        <div class="table-responsive">
           
                <table class="table" id="grid_1">
                    <thead>
                        <tr>
                            <th class="center"></th>
                                {foreach from=$columns item=column}
                                    <th class="center">
                                        <span class="title_box active">
                                        {$column.header}
                                        <a{if $order_by == $column.column && $order_way == 'desc'} class="active"{/if} 
                                        href="{$url|escape:'htmlall':'UTF-8'}&order_by={$column.column}&order_way=desc"
                                        >
                                            <i class="icon-caret-down"></i>
                                        </a>
                                        <a{if $order_by == $column.column && $order_way == 'asc'} class="active"{/if} 
                                        href="{$url|escape:'htmlall':'UTF-8'}&order_by={$column.column}&order_way=asc"
                                        >
                                            <i class="icon-caret-up"></i>
                                        </a>
                                        </span>
                                    </th>
                                {/foreach}
                            <th class="center"></th>
                        </tr>
                        <tr>
                            <th class="center"><span class="title_box active">--</span></th>
                                {foreach from=$columns item=column}
                                    <th class="center">
                                        {if $column.type =='search'}
                                        <input class="filter" type="text" value="{$column.value}" name="{$column.column}" onkeyup="searchEnter(event)">
                                        {/if}
                                    </th>
                                {/foreach}
                            <th class="center">
                                <button type="button" class="btn btn-success w-100" onclick="searchFilters()"><i class="icon-search"></i> szukaj</button>
                                <button type="button" class="btn btn-warning w-100 mt-1" onclick="searchClear()"><i class="icon-eraser"></i> wyczyść</button>
                            </th>
                        </tr>

                    </thead>
                    <tbody>
                        {foreach from=$rows item=row}
                        <tr>
                            <td>
                                <input type="checkbox" name="selected[]" value="{$row['id']}"/>
                            </td>
                            {foreach from=$row key=id item=item}
                                <td class="center">
                                    {if $id == 'id_shipment'}
                                        {if $item['show']}
                                            <a href="{$url}&id_shipment={$item['id']}" target="_blank">
                                                {$item['id']}
                                            </a>
                                        {else}
                                            {$item['id']}
                                        {/if}
                                    {else if $id =='error'}
                                       {if $item!=='null'}
                                       <span class="label-tooltip" data-toggle="tooltip" data-placement="bottom" data-html="true" title=""  data-original-title="<code>{$item}</code>"><i class="icon-info"></i> </span>
                                       {/if}
                                    {else}
                                        {$item}
                                    {/if}
                                </td>
                            {/foreach}
                            <td class="center">
                                <a title="podgląd" class="btn btn-default w-100" href="{$order_link|escape:'htmlall':'UTF-8'}&id_order={$row['id_order']}">
                                    <i class="icon-search-plus"></i> podgląd
                                </a>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="1">
                            <button type="button" class="btn btn-success w-100" onclick="searchFilters()"><i class="icon-send"></i> zleć etykiety</button>
                            </th>
                            <th colspan="{($columns|count)+1}">
                                {include file=$pagination_file identifier='Packages'}
                            </th>
                        </tr>
                    </tfoot>
                </table>
            
        </div>
    </form>
</div>

<div id="settings" class="panel">
    <form action="{$url}" class="form-horizontal" method="post">
        <div class="panel-heading">
            <i class="icon-file-text"></i> Ustawienia</span>
        </div>
        <div class="table-responsive">
                <div class="form-group">
                    <label class="control-label col-lg-3">
                        Link potwierdzenia
                    </label>
                    <div class="col-lg-9">
                        <input type="text" value="{$access_url}" readonly>
                    </div>
                </div>
                {foreach $fields as $field}
                    {if $field}
                        <div class="form-group"> 

                            {if isset($field.name)} 
                                {if isset($field.options)}
                                    <label class="control-label col-lg-3 {$field.required}" for="{$field.name}">
                                        {$field.label}
                                    </label>
                                    <div class="col-lg-9">
                                        <select id="{$field.name}" type="text" name="{$field.name}" value="{$field.value}">
                                            {foreach from=$field.options key="option_key" item="option"}
                                                <option value="{$option_key}" {if $field.value==$option_key}selected="selected"{/if}>{$option}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                {else}
                                    <label class="control-label col-lg-3 {$field.required}" for="{$field.name}">
                                        {$field.label}
                                    </label>
                                    <div class="col-lg-9">
                                        <input id="{$field.name}" type="text" name="{$field.name}" value="{$field.value}">
                                    </div>
                                {/if}
                            {else}
                            
                                <h4 style="width:100%;text-align:center;">{$field.label}</h4>
                            
                            {/if}
                            
                        </div>
                    {/if}
                {/foreach}
             
        </div>
        <div class="panel-footer">
            <button class="btn btn-default pull-right" name="saveModuleSettings" type="submit">
                <i class="process-icon-save"></i>
                Zapisz
            </button>
        </div>
    </form>
</div>
<style>
    .w-100{
     width:100%;
    }
    .mt-1{
        margin-top:10px;
    }
</style>
<script>

    function ajaxCheckLabels(){
        $.post('{$check_url}',{}).then(function(e){
            console.log(e)
        });
    }


    function searchEnter(e){
        var key = (e.keyCode ? e.keyCode : e.which);
		if (key == 13)
		{
            searchFilters()
        }
    }
    function searchFilters(){
        
        var filter ='';
        var filters = $('.filter');

        for(var i=0; i<filters.length;i++){
            if(filters[i].value){
                filter += '&f['+filters[i].name+']='+filters[i].value
            }
        }

        var selected = '';

        var selects = $('input[name^=selected]:checked');

        if(selects){
            for(var i=0; i<selects.length;i++){
                if(selects[i].value){
                    selected += selects[i].value+','
                }
            }
            selected = '&selected='+selected.slice(0,selected.length-1);
        }

        location='{$url}'+selected+filter;
        
    }
    function searchClear(){
        location='{$url}';
    }
</script>