function checkAll() {
var everyting=document.getElementById('post-checks');
var boxes=new Array();
for (var x=0;x<everyting.length;x++) {if (everyting[x].type=="checkbox") {boxes[boxes.length]=everyting[x];}}
//now all checkboxes are in the array boxes so lets check all of them...
//USE THE NEXT LINE TO CHECK THEM ALL
//for(var x=0;x<boxes.length;x++) {boxes[x].checked=true;}
//USE THE NEXT LINE TO UNCHECK THEM ALL
//for(var x=0;x<boxes.length;x++) {boxes[x].checked=false;}
//USE THE NEXT LINE TO INVERSE THEM ALL
for(var x=0;x<boxes.length;x++) {if (boxes[x].checked) {boxes[x].checked=false;}else {boxes[x].checked=true;}}
}

function testing (e,myId) {
var s = e.checked ? 'checked' : 'disabled';

document.getElementById(myId).className='plugin'+s

}

function activatePlugin(myId)
{
    document.getElementById(myId).className='pluginActive';
}

function deactivatePlugin(myId)
{
    document.getElementById(myId).className='pluginNonActive';
}

function disableAutocomplete()
{
    if (!document.getElementById) return false;
    var f = document.getElementById('login');
    var u = f.elements[0];
    f.setAttribute("autocomplete", "off");
    u.focus();
}
