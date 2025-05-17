<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Inline PHP Test</title></head>
<body>
<?php
function getFileId(): int { return 1; }

?>
<script id="xtemplate-script">
    console.log("FileId = " + <?= getFileId() ?>
        + "...");
</script>
</body>
</html>
