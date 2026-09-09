<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>eDemand log viewer</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.datatables.net/plug-ins/9dcbecd42ad/integration/bootstrap/3/dataTables.bootstrap.css">


    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        body {
            padding: 25px;
        }

        h1 {
            font-size: 1.5em;
            margin-top: 0;
        }

        .date {
            min-width: 75px;
        }

        .text {
            white-space: pre-wrap;
            word-break: break-word;
        }

        a.llv-active {
            z-index: 2;
            background-color: #f5f5f5;
            border-color: #777;
        }
        
        /* Make sure stack is always displayed */
        .stack {
            display: block !important;
            white-space: pre-wrap;
        }

        /* Ensure all expandable content is visible */
        .collapse {
            display: block !important;
        }
        
        /* Hide any toggle buttons */
        .expand-btn, .collapse-btn {
            display: none !important;
        }

        /* Style for the log content */
        #table-log td {
            vertical-align: top;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-3 col-md-2 sidebar">
            <h1><span class="glyphicon glyphicon-calendar" aria-hidden="true"></span> eDemand Log Viewer</h1>
            <div class="list-group">
                <?php if(empty($files)): ?>
                    <a class="list-group-item liv-active">No Log Files Found</a>
                <?php else: ?>
                    <?php foreach($files as $file): ?>
                        <a href="?f=<?= base64_encode($file); ?>"
                           class="list-group-item <?= ($currentFile == $file) ? "llv-active" : "" ?>">
                            <?= $file; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-9 col-md-10 table-container">
            <?php if(is_null($logs)): ?>
                <div>
                    <br><br>
                    <strong>Log file > 50MB, please download it.</strong>
                    <br><br>
                </div>
            <?php else: ?>
                <table id="table-log" class="table table-striped">
                    <thead>
                    <tr>
                        <th>Level</th>
                        <th>Date</th>
                        <th>Content</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php foreach($logs as $key => $log): ?>
                        <tr>
                            <td class="text-<?= $log['class']; ?>">
                                <span class="<?= $log['icon']; ?>" aria-hidden="true"></span>
                                &nbsp;<?= $log['level']; ?>
                            </td>
                            <td class="date"><?= $log['date']; ?></td>
                            <td class="text">
                                <?= htmlspecialchars($log['content']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div>
                <?php if($currentFile): ?>
                    <a href="?dl=<?= base64_encode($currentFile); ?>">
                        <span class="glyphicon glyphicon-download-alt"></span>
                        Download file
                    </a>
                    -
                    <a id="delete-log" href="?del=<?= base64_encode($currentFile); ?>"><span
                                class="glyphicon glyphicon-trash"></span> Delete file</a>
                    <?php if(count($files) > 1): ?>
                        -
                        <a id="delete-all-log" href="?del=<?= base64_encode("all"); ?>"><span class="glyphicon glyphicon-trash"></span> Delete all files</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.10.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/plug-ins/9dcbecd42ad/integration/bootstrap/3/dataTables.bootstrap.js"></script>
<script>
    $(document).ready(function () {
        // Removed the toggle click event to ensure logs are always displayed
        
        $('#table-log').DataTable({
            "order": [],
            "stateSave": true,
            "stateSaveCallback": function (settings, data) {
                window.localStorage.setItem("datatable", JSON.stringify(data));
            },
            "stateLoadCallback": function (settings) {
                var data = JSON.parse(window.localStorage.getItem("datatable"));
                if (data) data.start = 0;
                return data;
            }
        });
        $('#delete-log, #delete-all-log').click(function () {
            return confirm('Are you sure?');
        });
    });
</script>
</body>
</html>