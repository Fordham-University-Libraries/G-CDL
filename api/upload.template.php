<!DOCTYPE html>
<html>

<head>
    <title>Upload your files</title>
    <style>
        body {
            padding: 1em;
        }

        label span {
            color: darkred;
        }

        .icon {
            font-size: 1em;
        }

        .success {
            color: green;
        }

        .collapse {
            margin-bottom: 1em;
        }
    </style>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
</head>

<body>
    <h1>Hello <?= $user ?></h1>
    <h2>Adding item to <?= $libraryName ?> (<?= $libraryKey ?>) collection</h2>
    <p>
        <!-- help -->
        <button class="btn btn-outline-primary" type="button" data-toggle="collapse" data-target="#collapseHelp" aria-expanded="false" aria-controls="collapseHelp">Help</button>
        <?php if ($ilsApi['enable'] && $regex) : ?>
            <button class="btn btn-outline-info" type="button" data-toggle="collapse" data-target="#collapseIlsApi" aria-expanded="false" aria-controls="collapseIlsApi"><span class="icon success">&check;</span> ILS API</button>
        <?php endif ?>
    </p>
    <div class="collapse" id="collapseHelp">
        <div class="card card-body">
            <p>
                <?= $lang['helpText'] ?>
            </p>
        </div>
    </div>
    <!-- ils api help -->
    <div class="collapse" id="collapseIlsApi">
        <?php if ($ilsApi['enable'] && $regex) : ?>
            <div class="alert alert-info" role="alert">
                <p>This Library has ILS' API setup, you can just upload the file named with an itemId in a correct format <span class="badge badge-secondary regex"><?= $regex ?></span> and it'll auto populate the form! see Help for more info!</p>
                <a class="btn btn-secondary" target="_blank" href="https://regex101.com/?regex=<?= urlencode($trimmedRegex) ?>&testString=qwertyuiopasdfghjklzxcvbnm09876543211234567890.pdf">What does this <span class="badge badge-secondary regex"><?= $regex ?></span> mean?</a>
            </div>
        <?php endif ?>
    </div>

    <form id="upload-form" enctype="multipart/form-data" action="index.php" method="POST">
        <!-- <h2>Upload your file</h2> -->
        <div>
            <div id="bib">
                <h3>Bibliographic Info</h3>
                <div class="mb-3">
                    <label for="bibId" class="form-label">Bib ID <span>*</span></label>
                    <input required type="text" class="form-control" id="bibId" name="bibId">
                    <small class="form-text text-muted">Unique Record ID/Bibliographic ID of this item in your Library Management System. This field is used so the CDL app can group multiple items of that same "book" together</small>
                </div>
                <div class="mb-3">
                    <label for="itemId" class="form-label">Item ID <span>*</span></label>
                    <input required type="text" class="form-control" id="itemId" name="itemId">
                    <small class="form-text text-muted">Unique Item Records ID/Barcode of this item. This filed is used so the CDL app differentiate multiple copies of the same "book"</small>
                </div>
                <div class="mb-3">
                    <label for="title" class="form-label">Title <span>*</span></label>
                    <input required type="text" class="form-control" id="title" name="title">
                </div>
                <div class="mb-3">
                    <label for="author" class="form-label">Author</label>
                    <input type="text" class="form-control" id="author" name="author">
                </div>
            </div>

            <div id="multi">
                <h3>Multiple Parts Info</h3>
                <div class="alert alert-secondary">
                    This is for a case where
                    <ul>
                        <li>you need to break a single item into multiple files (most likely because the filesize is too large for Google PDF viewer)</li>
                        <li>you have multiple items that share the same bib record, but each items is different e.g. Encyclopedia with 26 books for each alphabets</li>
                    </ul>
                    Just leave these blanks if the above two scenarios do not apply. e.g. if you're uploading the 4th copy of the same book, just leave it blank, the app can tell it's a copy from existing bibIds in the system
                </div>
                <div class="mb-3">
                    <label for="part" class="form-label">Part #</label>
                    <input type="text" class="form-control" id="part" name="part">
                    <small class="form-text text-muted">part of this file e.g 1</small>
                </div>
                <div class="mb-3">
                    <label for="partTotal" class="form-label">of Total Part</label>
                    <input type="text" class="form-control" id="partTotal" name="partTotal">
                    <small class="form-text text-muted">total part of "thing/book" this item is part of e.g. 6</small>
                </div>
                <div class="mb-3">
                    <label for="partDesc" class="form-label">Part Description</label>
                    <input type="text" class="form-control" id="partDesc" name="partDesc">
                    <small class="form-text text-muted">a description so end users have more info e.g. "Chaper 1-13" or "Letter A-F"</small>
                </div>
            </div>

            <div id="itemId-exists-warning" style="display:none;" class="alert alert-danger" role="alert">
            </div>
        <div>
        <hr>
        <input type="hidden" name="action" value="upload"></input>
        <input type="hidden" name="libKey" value="<?=$libraryKey?>"></input>
        <table id="file-info">
            <tr><td>File name:</td><td id="fileName"></td></tr>
            <tr><td>Fize size:</td><td id="fileSize"></td></tr>
        </table>
        <input id="file" type="file" name="uploaded_file" accept="application/pdf" required></input><br /><br />
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" name="should_create_no_ocr" id="shouldCreateNoOcr" checked value="1">
            <label class="custom-control-label" for="shouldCreateNoOcr">Create a No-OCR version?</label>
        </div>
        <br>
        <input id="submit-button" class="btn btn-primary" type="submit" value="Upload"></input>
    </form>
    <div id="submitting" style="display:none; padding: 1em; background: aliceblue; margin-top: 1em;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Uploading...</span>
        </div>
        Uploading file and processing (get item metadata, generate no OCR version, and etc.)... this could take a few minutes... hang tight!
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" integrity="sha384-LtrjvnR4Twt/qOuYxE721u19sVFLVSA4hf/rRt6PrZTmiPltdZcI7q7PXQBYTKyf" crossorigin="anonymous"></script>

    <script>
        var uploadForm = document.getElementById("upload-form");
        uploadForm.onsubmit = function() {
            document.getElementById("submit-button").disabled = true;
            document.getElementById("submitting").style.display = 'block';
            //return false;
        }

        //on file change
        var uploadField = document.getElementById("file");
        uploadField.onchange = function() {
            let fileError = false;
            if (this.files[0].type != 'application/pdf') {
                alert("Please upload a .pdf file");
                fileError = true;
            }
            //console.log(this.files[0].size);
            //102.5MB since GDrive seems to be able to reduce the pdf down a tiny bit once uploaded
            //e.g. a 102.1MB file is down to 97MB on GDrive
            //the Drive viewer limit of 100MB is of the uploaded file un GDrive
            if (this.files[0].size > 1.025e+8) {
                alert("File is too big! Max file size is 102.5MB, try reducing it using Acrobat. If can't, let Witt knows, we'll have to manually break it down to mulitple files");
                fileError = true;
            }

            if (!fileError) {
                //console.log(this.files[0]);
                let fileName = this.files[0].name;
                document.getElementById("fileName").textContent = fileName;
                document.getElementById("fileSize").textContent = (this.files[0].size / 1e+6).toFixed(2) + ' MB';

                <?php if ($regex) : ?>
                    const regex = <?= $regex ?>;
                    const itemId = fileName.match(regex);
                    if (!itemId?.length > 1) {
                        alert("can't find itemId from file name");
                    } else {
                        document.getElementById("itemId").textContent = itemId[1];
                        document.getElementById("file-info").style.display = 'block';
                        <?php if ($ilsApi['enable']) : ?>
                            getBibByItemId(itemId[1]);
                        <?php endif ?>
                        checkCdlItemAlreadyInSystem(itemId[1]);
                    }
                <?php endif ?>

                //check part
                const partRegex = /\[(\d)+.?of.?(\d)+\]/;
                const parts = fileName.match(partRegex);
                if (parts && parts.length) {
                    document.getElementById("part").value = parts[1];
                    document.getElementById("partTotal").value = parts[2];
                }

            } else {
                uploadField.value = '';
                document.getElementById("fileName").textContent = '';
                document.getElementById("itemId").textContent = '';
                document.getElementById("fileSize").textContent = '';
            }
        };

        function getBibByItemId(itemId) {
            fetch(`index.php?action=get_ils_bib&keyType=itemId&key=${itemId}&libKey=<?= $libraryKey ?>`)
                .then(response => {
                    return response.text();
                }).then(function(json) {
                    bib = JSON.parse(json).data;
                    //console.log(bib);
                    document.getElementById("bibId").value = bib.bibId;
                    document.getElementById("itemId").value = bib.itemId ? bib.itemId : itemId;
                    document.getElementById("title").value = bib.title;
                    document.getElementById("author").value = bib.author;
                    
                    //document.getElementById("numItems").innerHTML = bib.items.length;
                });
        }

        function checkCdlItemAlreadyInSystem(itemId) {
            fetch(`index.php?action=get_items&keyType=itemId&key=${itemId}&libKey=<?= $libraryKey ?>`)
                .then(response => {
                    return response.text();
                }).then(function(json) {
                    cdlItem = JSON.parse(json).data;
                    if (!cdlItem.error) {
                        cdlItem.forEach(item => {
                            if (itemId == itemId) {
                                //console.log('dupe');
                                document.getElementById("itemId-exists-warning").innerHTML = `An item with item ID: ${itemId} already exists in the CDL app, unless this a multi-part item where you need to break one book into multiple PDFs, then this is a <a href="https://controlleddigitallending.org/faq#:~:text=Does%20CDL%20support%20a%20library%20lending%20more%20copies%20than%20it%20owns" target="_blank">bad idea!</a> Are you sure you want to do this?`;
                                document.getElementById("itemId-exists-warning").style.display = 'block';
                                return;
                            }
                        })
                    }
                });
        }
    </script>
</body>

</html>