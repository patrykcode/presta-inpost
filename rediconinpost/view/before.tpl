<script src="https://geowidget.easypack24.net/js/sdk-for-javascript.js" type="text/javascript"></script>
<link rel="stylesheet" href="https://geowidget.easypack24.net/css/easypack.css">
<script>
var ajaxUrl = '{$ajaxUrl}';
var setPointAbort = null;
$(document).ready(function(){
    $('body').on('click','input[id="delivery_option_{$inpostID}"]',function(){  

           if(typeof easyPack == "object"){

            trigerEasyPack();

            easyPack.modalMap(function(point, modal) {
                modal.closeModal();
                if(point){
                        setPointAbort = $.ajax({
                        url: ajaxUrl+'?action=addPoint',
                        data:{ paczkomat : point , c:{$cart}},
                        dataType: 'json',
                        method: 'get',
                        beforeSend(){
                            setPointAbort.abort();
                        },
                        success: function(data) {
                            console.log(data)

                            $('span[id="selected-point"]').html('Wybrany paczkomat: '+point['name'] + ' -'+point.address.line1 + ' , ' + point.address.line2)
                        }
                    });
                }
            }, { width: 500, height: 600 });

           } 
            
        
    });
});
$('[id="confirm_order"]').on('click',function(e){
        if($('input[id="delivery_option_{$inpostID}"]:checked').length){
            if($('[id="selected-point"]').html()==''){
                e.preventDefault();
                alert('proszę wybrac paczkomat');
                return false;
            }else{
                confirmOrder($(this));
                return true;
            }
        }
})
function trigerEasyPack(){
    easyPack.init({
        defaultLocale: 'pl',
        points: {
            types: ['parcel_locker'] 
        },
        map: {
            defaultLocation: [52.404261, 19.281642],
            initialTypes: ['parcel_locker']
        }
    });
}
</script>