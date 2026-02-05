
function postLTI(ses,name){
console.log(ses)
var dfd = jQuery.Deferred();
$.post( `/LTI/postLTI.php?name=${name}`, {data:ses} ).done(function(result){

dfd.resolve(result)


});
//setTimeout(function(){dfd.resolve("made it")},5000);
 return dfd.promise();

}
