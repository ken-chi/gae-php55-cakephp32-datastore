<?php
    $q = htmlspecialchars($q);
    $validator = $table->validationDefault(new Cake\Validation\Validator());
    $searchConfig = [];
    foreach ($table->getColumns() as $col => $conv) {
        $searchConfig[$col] = $conv;
    }
    // [value]=value
    $columns = array_keys($table->getColumns());
    $columns = array_combine($columns, $columns);
    $searchQuery = explode('__', $q);
?>
<style>
  .modal-title #modal-success {background-color:#00CCFF;}
  .modal-title #modal-error {background-color:#FF0000;}
</style>
<script type="text/javascript">
    var searchConfig = <?=json_encode($searchConfig);?>;
    var datetimepickerConf = {i18n:{en:{months:[1,2,3,4,5,6,7,8,9,10,11,12]}}};

    $(document).ready(function(){
        // click UPDATE button
        $(".btn-update").on("click", function() {
            // get target id from attribute name "target" of button
            var id = $(this).attr("target");
            $("#modal-regist-label").text(id);
            $("#regist").attr("target", id);

            // get children tag <td> of target id, and set form
            $("#id-"+id).children("td").each(function(i){
                var column = $(this).attr("column");
                var value = $(this).attr("value");
                if (!column) return true;
                $(".regist[name="+column+"]").val(value);
            });
            $("#modal-regist").modal();
        });

        // click INSERT button
        $("#btn-insert").on("click", function() {
            // clear form
            $("#modal-regist-label").text("new");
            $(".regist").each(function(i){
                console.log($(this));
                $(this).val("");
            });
            $("#modal-regist").modal();
        });

        // click regist button
        $("#regist").on("click", function(){
            // get target id from attribute name "target" of button
            var id = $(this).attr("target");
            // request param
            var param = ["id="+id];
            $(".regist").each(function(i){
                var column = $(this).attr("name");
                console.log(column);
                if (column) param.push(column+"="+$(this).val());
            });
            $("#modal-regist").modal("hide");
            console.log(param);
            req.open("POST", "./regist/?q=<?=$q;?>", true);
            req.setRequestHeader("content-type", "application/x-www-form-urlencoded;charset=UTF-8");
            req.send(param.join("&"));
        });

        // change search select
        $("#search-column").on("change", function() {
            var column = $(this).val();
            var conf = searchConfig[column];

            $("#search-area").empty();
            var dom = null;
            if (conf && conf.type && conf.type == "dateTime") {
                // start datetimepicker
                var tmp = $('<input id="search-target-start" type="text" />');
                datetimepickerConf["onShow"] = function( ct ){this.setOptions({maxDate:$("#search-target-end").val()?jQuery("#search-target-end").val():false})};
                tmp.datetimepicker(datetimepickerConf);
                $("#search-area").append(tmp);

                // end datetimepicker
                dom = $('<input id="search-target-end" type="text" name="'+column+'"/>');
                datetimepickerConf["onShow"] = function( ct ){this.setOptions({minDate:jQuery("#search-target-start").val()?$("#search-target-start").val():false})};
                dom.datetimepicker(datetimepickerConf);

            } else if (conf && conf.type && conf.type == "select") {
                // select
                dom = $('<select id="search-target">');
                $.each(conf["list"], function(i, v){
                    dom.append($('<option value="'+i+'">'+v+'</option>'));
                });
            } else {
                // text
                dom = $('<input id="search-target" type="text" />');
            }
            $("#search-area").append(dom);
        });

        // click search button
        $("#btn-search").on("click", function() {
            var column = $("#search-column").val();
            var start = $("#search-target").val() ? $("#search-target").val() : $("#search-target-start").val();
            var end = $("#search-target-end").val() ? $("#search-target-end").val() : "";
            if (column && (start || end)) {
                if ($("#search-target").prop("tagName") == "SELECT") end = "equal";
                location.href="./?q=" + $("#search-column").val() + "__" + start + "__" + end;
                return;
            }
            if ("<?=$q;?>") location.href="./";
        });

        // click delete button
        $("#btn-delete").on("click", function() {
            var ary = [];
            $(".delete").each(function(){
                if ($(this).prop("checked")) ary.push($(this).val());
            });
            if (!ary.length) return;
            var param = ["id="+ary.join(",")];
            console.log(param);
            req.open("POST", "./delete/?q=<?=$q;?>", true);
            req.setRequestHeader("content-type", "application/x-www-form-urlencoded;charset=UTF-8");
            req.send(param.join("&"));
        });

        // check all
        $("#all").on("click", function() {
            $(".delete").prop("checked", $(this).prop("checked"));
        });

        // default search setting
        $("#search-column").val("<?=isset($searchQuery[0]) ? $searchQuery[0] : "id";?>");
        $("#search-column").trigger("change");
        if ("<?=(isset($searchConfig[$searchQuery[0]]['type'])) ? $searchConfig[$searchQuery[0]]['type'] : '';?>" == "dateTime") {
            $("#search-target-start").val("<?=isset($searchQuery[1]) ? $searchQuery[1] : '';?>");
            $("#search-target-end").val("<?=isset($searchQuery[2]) ? $searchQuery[2] : '';?>");
        } else $("#search-target").val("<?=isset($searchQuery[1]) ? $searchQuery[1] : '';?>");

        // datetimepicker
        $(".datetimepicker").datetimepicker(datetimepickerConf);
    });

    var req = new XMLHttpRequest();
    window.onload = function() {
      req.onreadystatechange = function() {
        if (req.readyState == 4) {
          if (req.status == 200) {
            var result = JSON.parse(req.responseText)
            console.log(result);

            var dialog;
            if (result.res == "error") {
              // dialog_error
              error(result.res, result.message);
              $('#modal-error').modal();
              $("#modal-regist").modal();
            } else {
              // dialog_success
              $("#modal-success-label").text(result.message);
              $("#modal-success").modal();
              location.reload();
            }
          } else {
            console.log(req);
            error(req.statusText, {});
            $('#modal-error').modal();
          }
        }
      }
    }

    function error(h1, msg) {
      var table = document.querySelectorAll("#modal-error tbody")[0];
      table.innerHTML = "";
      document.querySelectorAll("#modal-error h4")[0].innerHTML = h1;
      if (msg) {
        Object.keys(msg).forEach(function(key) {
          var row = table.insertRow(-1);
          row.insertCell(-1).innerHTML = key;
          row.insertCell(-1).innerHTML = msg[key][Object.keys(msg[key])[0]];
        });
      }
    }
</script>
<div>
  <?=$this->Form->select('search', $columns, ['id' => 'search-column']);?>
  <span id="search-area">
  </span>
  <button class="btn btn-info btn-outline-secondary" id="btn-search" data-toggle="tooltip" title="SEARCH"><i class="fas fa-search"></i></button>
</div>

<button id="btn-insert" class="btn btn-primary btn-outline-secondary" data-toggle="tooltip" title="INSERT DATA"><i class="fas fa-plus-square"></i></button>
<button id="btn-delete" class="btn btn-denger btn-outline-secondary" data-toggle="tooltip" title="DELETE CHECKED DATA"><i class="fas fa-trash-alt"></i></button>

<table class="table table-bordered table-striped table-hover">
  <thead class="thead-dark">
    <tr>
      <th><?=$this->Form->checkbox('all', ['id' => 'all']);?></th>
<?php
  foreach ($table->getColumns() as $col => $conv) {
?>
      <th><?=$col;?></th>
<?php
  }
?>
      <th>update</th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($list as $row) {
    $id = null;
?>
    <tr id="id-<?=$row->id;?>">
<?php
    foreach ($table->getColumns() as $col => $conv) {
        if (!$id) {
            $id = $row->$col;
            echo '<td>' . $this->Form->checkbox('id', ['value' => $id, 'class' => 'delete']) . '</td>';
        }
?>
      <td column="<?=$col;?>" value="<?=$row->$col;?>">
<?php
        if (isset($conv['list'][$row->$col])) echo $conv['list'][$row->$col];
        else if ($col != 'password') echo $row->$col;
?>
      </td>
<?php
    }
?>
      <td><button target="<?=$id;?>" class="btn-update btn btn-warning btn-outline-secondary" data-toggle="tooltip" title="EDIT"><i class="fas fa-edit"></i></button></td>
    </tr>
<?php } ?>
  <tbody>
</table>

<a class="badge badge-primary" href="?p=<?=($p>0)?$p-1:$p;?>&q=<?=$q;?>&limit=<?=$limit;?>"><i class="fas fa-chevron-left"></i></a> / <?=$p+1;?> / <a class="badge badge-primary" href="?p=<?=$p+1;?>&q=<?=$q;?>&limit=<?=$limit;?>""><i class="fas fa-chevron-right"></i></a> ( <?=$p*$limit+1;?> - <?=(($p+1)*$limit < $count) ? ($p+1)*$limit : $count;?> / <?=$count;?> )



<!-------- dialog --------->
<div class="modal" id="modal-regist" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">
            <div class="modal-header bg-success">
                <h4 class="modal-title" id="modal-regist-label"></h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
<?php
    $id = 0;
    foreach ($table->getColumns() as $col => $conv) {
        if (!$id) {
            $id = $col;
            echo $this->Form->hidden($col, ['class' => 'regist']);
            continue;
        }
?>
        <label><?=$col;?> <?=$this->Datastore->inputTypes($validator[$col]);?></label>
        <?=$this->Datastore->input($col, ['class' => 'regist', 'conf' => $conv]);?>
<?php
    }
?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-lg" data-dismiss="modal" id="regist">Regist</button>
                <button type="button" class="btn btn-dark btn-lg" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="modal-success" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">
            <div class="modal-header bg-success">
                <h4 class="modal-title" id="modal-success-label"></h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark btn-lg" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="modal-error" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h4 class="modal-title" id="modal-label"></h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-hover">
                  <thead class="thead-light">
                    <tr><th>Column</th><th>Error</th></tr>
                  </thead>
                  <tbody id="error_msg" />
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark btn-lg" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
