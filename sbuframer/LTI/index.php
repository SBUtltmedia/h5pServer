 <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
 <script src="js/grading.js"></script>
 <script>
 <?
$ses = $_POST;
print "var ses=".json_encode($ses);

?>
;postLTI(ses).then(function(result){alert(result)})
</script>
