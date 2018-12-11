function ASTORE_toggle(cbox, id, field) {
    oldval = cbox.checked ? 0 : 1;
    var dataS = {
        "action" : "toggle",
        "id": id,
        "field": field,
        "oldval": oldval,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/astore/ajax.php",
        data: data,
        success: function(result) {
            cbox.checked = result.newval == 1 ? true : false;
            try {
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.statusMessage);
            }
        },
        error: function(jqxhr, status, exception) {
             console.log(status);
        }
    });
    return false;
}
