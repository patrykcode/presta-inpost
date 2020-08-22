
<div id="formAddPaymentPanel" class="panel">
        <div class="panel-heading">
            <i class="icon-file-text"></i> Redicon - inpost</span>
        </div>
        <div class="table-responsive">
            {if $order_info}
                
                <ul>
                    <li>{$order_info.point_code}</li>
                    <li>{$order_info.point_address1}</li>
                    <li>{$order_info.point_address2}</li>
                    <li>Status etykiety: {$order_info.event}</li>
                    <li>Numer śledzenia: {$order_info.shipping_number}</li>
                </ul>

                {if $order_info.id_shipment && $order_info.pdf}
                    <a href="{$url}{$order_info['id_shipment']}" target="_blank">pokaż {$order_info.pdf}</a>
                {else}
                    {if $order_info.id_shipment}
                        <button class="btn btn-default" type="button" onclick="confirmShipment('{$order_info.id_shipment}')">potwierdź etykiete</button>
                        <button class="btn btn-default" type="button" onclick="downloadPdf()">pobierz pdf</button><br/>
                    {/if}
                    {if $order_info.error}
                        ERROR:
                        <pre><code>{$order_info.error}</code></pre>
                    {/if}
                {/if}

            {/if}
        </div>

        <script>
            function confirmShipment(id_shipment){
                $.post('{$access_url}',{
                        "event_ts":"2015-12-08 19:42:42 +0100",
                        "event":"shipment_confirmed",
                        "organization_id":1,
                        "payload": {
                            "shipment_id":id_shipment,
                            "tracking_number":(new Date()).getTime()
                        }
                }).then(function(e){
                    alert(e)
                });
            }
            function downloadPdf(){
                $.post('{$check_url}',{}).then(function(e){
                    alert(e)
                });
            }
        </script>
</div>
