<?php
session_start(); // Start session for login management

$conn = new mysqli("localhost","root","","livestock");
if ($conn->connect_error) die("Connection failed");

// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Handle login
if(isset($_POST['login'])){
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if($username === 'admin' && $password === '123'){
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header("Location: ?page=home");
        exit();
    } else {
        $error_message = "Invalid username or password!";
    }
}

// Handle logout
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ?page=login");
    exit();
}

// If not logged in, show login page
if(!$isLoggedIn && (!isset($_GET['page']) || $_GET['page'] != 'login')){
    header("Location: ?page=login");
    exit();
}

$page = $_GET['page'] ?? 'home';
$message = "";
$error_message = "";

/* ====================== STATS ====================== */
$totalAnimals = $conn->query("SELECT COUNT(*) as t FROM Animals")->fetch_assoc()['t'];
$totalHealth = $conn->query("SELECT COUNT(*) as t FROM health_records")->fetch_assoc()['t'];

// Get active sick animals count
$activeSick = $conn->query("SELECT COUNT(*) as t FROM health_records WHERE type='Sick' AND (endDate >= CURDATE() OR endDate IS NULL)")->fetch_assoc()['t'];

// Get active pregnant animals count
$activePregnant = $conn->query("SELECT COUNT(*) as t FROM health_records WHERE type='Pregnant' AND (endDate >= CURDATE() OR endDate IS NULL)")->fetch_assoc()['t'];

/* ====================== ANIMAL CRUD ====================== */
if(isset($_POST['save_animal'])){
    $stmt=$conn->prepare("INSERT INTO Animals(tagId,name,animalType,sex,breed,birthdate,ownerContact) VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $_POST['tag'],$_POST['name'],$_POST['type'],$_POST['sex'], $_POST['breed'],$_POST['date'],$_POST['owner'] );
    if($stmt->execute()){
        $message = "Animal added successfully!";
    } else {
        $error_message = "Error: ".$stmt->error;
    }
}

if(isset($_POST['update_animal'])){
    $stmt=$conn->prepare("UPDATE Animals SET name=?,animalType=?,sex=?,breed=?,birthdate=?,ownerContact=? WHERE tagId=?");
    $stmt->bind_param("sssssss", $_POST['name'],$_POST['type'],$_POST['sex'], $_POST['breed'],$_POST['date'],$_POST['owner'],$_POST['tag'] );
    $stmt->execute();
    $message = "Animal updated successfully!";
}

if(isset($_GET['delete_animal'])){
    // First delete related health records
    $stmt2 = $conn->prepare("DELETE FROM health_records WHERE tagId=?");
    $stmt2->bind_param("s", $_GET['delete_animal']);
    $stmt2->execute();
    
    // Then delete the animal
    $stmt=$conn->prepare("DELETE FROM Animals WHERE tagId=?");
    $stmt->bind_param("s",$_GET['delete_animal']);
    $stmt->execute();
    $message = "Animal deleted successfully!";
}

$editAnimal=null;
if(isset($_GET['edit_animal'])){
    $r=$conn->query("SELECT * FROM Animals WHERE tagId='".$conn->real_escape_string($_GET['edit_animal'])."'");
    $editAnimal=$r->fetch_assoc();
}

/* ====================== HEALTH CRUD ====================== */

// Health record validation - TAG must exist in Animals
if(isset($_POST['save_health']) || isset($_POST['update_health'])){
    $tag = $_POST['tag'];
    
    // Check if tag exists in Animals
    $checkTag = $conn->prepare("SELECT tagId FROM Animals WHERE tagId=?");
    $checkTag->bind_param("s", $tag);
    $checkTag->execute();
    $resultTag = $checkTag->get_result();
    
    if($resultTag->num_rows == 0){
        // Tag not found in Animals - error message
        $error_message = "Error: Tag ID '".$tag."' not found in Animals! Please add the animal first before creating health records.";
    } else {
        // Tag exists - proceed with save/update
        if(isset($_POST['save_health'])){
            $stmt=$conn->prepare("INSERT INTO health_records(tagId,type,startDate,endDate,nextEventDate,notes,vetName,vetContact) VALUES(?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssss", $tag, $_POST['type'], $_POST['start'], $_POST['end'], $_POST['next'], $_POST['notes'], $_POST['vet'], $_POST['contact']);
            if($stmt->execute()){
                $message = "Health record added successfully for tag: ".$tag;
            } else {
                $error_message = "Error: ".$stmt->error;
            }
        } else if(isset($_POST['update_health'])){
            $stmt=$conn->prepare("UPDATE health_records SET tagId=?,type=?,startDate=?,endDate=?,nextEventDate=?,notes=?,vetName=?,vetContact=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $tag, $_POST['type'], $_POST['start'], $_POST['end'], $_POST['next'], $_POST['notes'], $_POST['vet'], $_POST['contact'], $_POST['id']);
            $stmt->execute();
            $message = "Health record updated successfully!";
        }
    }
}

if(isset($_GET['delete_health'])){
    $stmt=$conn->prepare("DELETE FROM health_records WHERE id=?");
    $stmt->bind_param("i",$_GET['delete_health']);
    $stmt->execute();
    $message = "Health record deleted successfully!";
}

$editHealth=null;
if(isset($_GET['edit_health'])){
    $r=$conn->query("SELECT * FROM health_records WHERE id=".intval($_GET['edit_health']));
    $editHealth=$r->fetch_assoc();
}

/* ====================== FUNCTION TO GET BREEDS ====================== */
function getBreeds($type){
    $options = [];
    switch($type){
        case "Cow": $options = ["Ankole","Friesian","Jersey","Local Breed"]; break;
        case "Goat": $options = ["Boer","Local Goat","Saanen"]; break;
        case "Sheep": $options = ["Dorper","Merino","Local Sheep"]; break;
        case "Pig": $options = ["Large White","Landrace","Local Pig"]; break;
        case "Chicken": $options = ["Broiler","Layer","Local Chicken"]; break;
    }
    return $options;
}

/* ====================== LOGIN PAGE ====================== */
if($page == 'login'):
?>
<!DOCTYPE html>
<html>
<head>
<title>Login - Livestock Management System</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial;background:linear-gradient(135deg, #27ae60, #2c3e50);height:100vh;display:flex;justify-content:center;align-items:center}
.login-container{background:white;padding:40px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,0.2);width:350px}
.login-container h2{text-align:center;color:#27ae60;margin-bottom:30px}
.login-container input{width:100%;padding:12px;margin:10px 0;border:1px solid #ddd;border-radius:5px}
.login-container button{width:100%;padding:12px;background:#27ae60;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px}
.login-container button:hover{background:#219a52}
.error{background:#f8d7da;color:#721c24;padding:10px;border-radius:5px;margin-bottom:15px;text-align:center}
</style>
</head>
<body>
<div class="login-container">
<h2> Livestock Management System</h2>
<h3 style="text-align:center;margin-bottom:20px">Login</h3>
<?php if(isset($error_message)): ?>
<div class="error"> <?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>
<form method="POST">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit" name="login">Login</button>
</form>
<p style="text-align:center;margin-top:20px;color:#666">Demo: admin / 123</p>
</div>
</body>
</html>
<?php
exit();
endif;
?>

<!DOCTYPE html>
<html>
<head>
<title>Livestock Management System</title>
<style>
body{font-family:Arial;background:#f4f6f9}
.container{width:90%;margin:auto}
.header{display:flex;justify-content:space-between;align-items:center;background:#2c3e50;padding:10px 20px;border-radius:8px;margin-bottom:20px;color:white}
.header h1{margin:0}
.logout-btn{background:#e74c3c;color:white;padding:8px 15px;text-decoration:none;border-radius:5px}
.logout-btn:hover{background:#c0392b}
.card{display:inline-block;padding:20px;margin:10px;background:#27ae60;color:white;text-decoration:none;border-radius:8px;transition:transform 0.3s}
.card:hover{transform:scale(1.05)}
form{background:#fff;padding:20px;margin:10px 0;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1)}
input,select{width:100%;padding:8px;margin-top:5px;border:1px solid #ddd;border-radius:4px}
table{width:100%;border-collapse:collapse;margin-top:10px;background:white}
th,td{border:1px solid #ddd;padding:10px;text-align:center}
th{background:#27ae60;color:white}
.back-btn{margin-top:15px;display:inline-block;padding:10px 15px;background:#555;color:white;text-decoration:none;border-radius:5px}
.success{background:#d4edda;color:#155724;padding:10px;margin:10px 0;border-radius:5px;border:1px solid #c3e6cb}
.error{background:#f8d7da;color:#721c24;padding:10px;margin:10px 0;border-radius:5px;border:1px solid #f5c6cb}
.warning{background:#fff3cd;color:#856404;padding:10px;margin:10px 0;border-radius:5px;border:1px solid #ffeeba}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-top:20px}
.stat-card{background:white;padding:25px;border-radius:10px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
.stat-card h3{color:#27ae60;font-size:36px;margin:10px 0}
.stat-card p{color:#666;font-size:16px}
</style>
<script>
function updateBreeds(){
    let type=document.getElementById("typeSelect").value;
    let breedInput=document.getElementById("breedInput");
    let breeds = [];
    switch(type){
        case "Cow": breeds=["Ankole","Friesian","Jersey","Local Breed"]; break;
        case "Goat": breeds=["Boer","Local Goat","Saanen"]; break;
        case "Sheep": breeds=["Dorper","Merino","Local Sheep"]; break;
        case "Pig": breeds=["Large White","Landrace","Local Pig"]; break;
        case "Chicken": breeds=["Broiler","Layer","Local Chicken"]; break;
    }
    let dataList = document.getElementById("breedList");
    dataList.innerHTML = "";
    breeds.forEach(b=>{
        let opt=document.createElement("option");
        opt.value=b;
        dataList.appendChild(opt);
    });
    if(!breeds.includes(breedInput.value)) breedInput.value="";
}
</script>
</head>
<body>
<div class="container">
<div class="header">
<h1> Livestock Management System</h1>
<div>
<span style="margin-right:15px">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</span>
<a href="?logout=1" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"> Logout</a>
</div>
</div>

<a href="?page=home" class="card"> Dashboard</a>
<a href="?page=all" class="card"> All Data</a>
<a href="?page=animals" class="card"> Animals: <?= $totalAnimals ?></a>
<a href="?page=records" class="card"> Health Records: <?= $totalHealth ?></a>

<?php if($message): ?>
<div class="success"> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if($error_message): ?>
<div class="error"> <?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<!-- ================= HOME / DASHBOARD ================= -->
<?php if($page=='home'): ?>
<h2> Dashboard Overview</h2>
<div class="stats-grid">
<div class="stat-card">
<p> Total Animals</p>
<h3><?= $totalAnimals ?></h3>
</div>
<div class="stat-card">
<p> Health Records</p>
<h3><?= $totalHealth ?></h3>
</div>
<div class="stat-card">
<p>Active Sick Animals</p>
<h3><?= $activeSick ?></h3>
</div>
<div class="stat-card">
<p> Active Pregnant Animals</p>
<h3><?= $activePregnant ?></h3>
</div>
</div>

<div style="background:white;padding:20px;border-radius:10px;margin-top:20px">
<h3>Guide:</h3>
<ul style="margin-left:20px;line-height:1.8">
<li>Click on <strong>Animals</strong> to add or manage animals</li>
<li>Click on <strong>Health Records</strong> to add or manage health records</li>
<li>Use the <strong>All Data</strong> page to view complete information</li>
</ul>
</div>

<div style="background:white;padding:20px;border-radius:10px;margin-top:20px">
<h3> Recent Health Records</h3>
<table>
<tr><th>Tag</th><th>Animal Name</th><th>status</th><th>Start Date</th><th>End Date</th></tr>
<?php 
$recent = $conn->query("SELECT h.*, a.name as animal_name FROM health_records h LEFT JOIN Animals a ON h.tagId = a.tagId ORDER BY h.id DESC LIMIT 5");
while($r = $recent->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r['tagId']) ?></td>
<td><?= htmlspecialchars($r['animal_name'] ?? 'N/A') ?></td>
<td><?= htmlspecialchars($r['type']) ?></td>
<td><?= htmlspecialchars($r['startDate']) ?></td>
<td><?= htmlspecialchars($r['endDate']) ?></td>
</tr>
<?php endwhile; ?>
<?php if($recent->num_rows == 0): ?>
<tr><td colspan="5">No health records found</td></tr>
<?php endif; ?>
</table>
</div>
<?php endif; ?>

<!-- ================= ALL ================= -->
<?php if($page=='all'): ?>
<h2> All Data Overview</h2>
<h3> Animals</h3>
<table>
<tr><th>Tag</th><th>Name</th><th>Type</th><th>Breed</th><th>Sex</th><th>Birthdate</th><th>Owner</th></tr>
<?php $res=$conn->query("SELECT * FROM Animals"); while($r=$res->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r['tagId']) ?></td>
<td><?= htmlspecialchars($r['name']) ?></td>
<td><?= htmlspecialchars($r['animalType']) ?></td>
<td><?= htmlspecialchars($r['breed']) ?></td>
<td><?= htmlspecialchars($r['sex']) ?></td>
<td><?= htmlspecialchars($r['birthdate']) ?></td>
<td><?= htmlspecialchars($r['ownerContact']) ?></td>
</tr>
<?php endwhile; ?>
</table>

<h3> Health Records</h3>
<table>
<tr><th>ID</th><th>Tag</th><th>Status</th><th>Start Date</th><th>End Date</th><th>Next Event</th><th>Vet</th></tr>
<?php $res=$conn->query("SELECT * FROM health_records"); while($r=$res->fetch_assoc()): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['tagId']) ?></td>
<td><?= htmlspecialchars($r['type']) ?></td>
<td><?= htmlspecialchars($r['startDate']) ?></td>
<td><?= htmlspecialchars($r['endDate']) ?></td>
<td><?= htmlspecialchars($r['nextEventDate']) ?></td>
<td><?= htmlspecialchars($r['vetName']) ?></td>
</tr>
<?php endwhile; ?>
</table>
<a href="?page=home" class="back-btn">← Back to Dashboard</a>
<?php endif; ?>

<!-- ================= ANIMALS ================= -->
<?php if($page=='animals'): ?>
<h2> Manage Animals</h2>
<?php if($editAnimal): ?>
<div class="warning"> Editing animal: <strong><?= htmlspecialchars($editAnimal['tagId']) ?></strong></div>
<?php endif; ?>
<form method="POST">
<input name="tag" value="<?= htmlspecialchars($editAnimal['tagId']??'') ?>" placeholder="Tag ID (unique)" required>
<input name="name" value="<?= htmlspecialchars($editAnimal['name']??'') ?>" placeholder="Animal Name">
<select name="type" id="typeSelect" onchange="updateBreeds()" required>
<option value="">Select Type</option>
<?php $types = ["Cow","Goat","Sheep","Pig","Chicken"]; foreach($types as $t){
$sel = ($editAnimal['animalType']??"")==$t?'selected':''; echo "<option value='$t' $sel>$t</option>"; } ?>
</select>
<select name="sex" required>
<option value="Male" <?= (isset($editAnimal['sex']) && $editAnimal['sex']=="Male")?"selected":"" ?>>Male</option>
<option value="Female" <?= (isset($editAnimal['sex']) && $editAnimal['sex']=="Female")?"selected":"" ?>>Female</option>
</select>
<input list="breedList" name="breed" id="breedInput" value="<?= htmlspecialchars($editAnimal['breed']??'') ?>" placeholder="Breed">
<datalist id="breedList">
<?php $breedOptions = getBreeds($editAnimal['animalType']??""); foreach($breedOptions as $b) echo "<option>$b</option>"; ?>
</datalist>
<input type="date" name="date" value="<?= htmlspecialchars($editAnimal['birthdate']??'') ?>" placeholder="Birth Date">
<input name="owner" value="<?= htmlspecialchars($editAnimal['ownerContact']??'') ?>" placeholder="Owner Contact">
<button type="submit" name="<?= $editAnimal?'update_animal':'save_animal' ?>" style="background:#27ae60;color:white;border:none;padding:10px;cursor:pointer;border-radius:5px"> 
    <?= $editAnimal?'Update Animal':'Save Animal' ?> 
</button>
<?php if($editAnimal): ?>
<a href="?page=animals" style="display:inline-block;margin-left:10px;padding:10px;background:#999;color:white;text-decoration:none;border-radius:5px">Cancel Edit</a>
<?php endif; ?>
</form>

<h3>Existing Animals</h3>
<table>
<tr><th>Tag</th><th>Name</th><th>Type</th><th>Breed</th><th>Sex</th><th>Birthdate</th><th>Owner</th><th>Actions</th></tr>
<?php $res=$conn->query("SELECT * FROM Animals ORDER BY tagId"); while($r=$res->fetch_assoc()): ?>
<tr>
<td><strong><?= htmlspecialchars($r['tagId']) ?></strong></td>
<td><?= htmlspecialchars($r['name']) ?></td>
<td><?= htmlspecialchars($r['animalType']) ?></td>
<td><?= htmlspecialchars($r['breed']) ?></td>
<td><?= htmlspecialchars($r['sex']) ?></td>
<td><?= htmlspecialchars($r['birthdate']) ?></td>
<td><?= htmlspecialchars($r['ownerContact']) ?></td>
<td>
<a href="?page=animals&edit_animal=<?= urlencode($r['tagId']) ?>" style="color:#27ae60">✏️ Edit</a> | 
<a href="?page=animals&delete_animal=<?= urlencode($r['tagId']) ?>" onclick="return confirm('Are you sure you want to delete animal <?= htmlspecialchars($r['tagId']) ?>? This will also delete all related health records.')" style="color:#e74c3c">🗑️ Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<a href="?page=home" class="back-btn">← Back to Dashboard</a>
<?php endif; ?>

<!-- ================= HEALTH RECORDS ================= -->
<?php if($page=='records'): ?>
<h2> Manage Health Records</h2>
<div class="warning">
 <strong>Important:</strong> 
</div>

<?php if($editHealth): ?>
<div class="warning"> Editing health record ID: <?= $editHealth['id'] ?></div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="id" value="<?= $editHealth['id']??'' ?>">

<label>Animal Tag (must exist in Animals):</label>
<input list="tags" name="tag" value="<?= htmlspecialchars($editHealth['tagId']??'') ?>" placeholder="Select or type Tag ID" required style="border:2px solid #27ae60">
<datalist id="tags">
<option value="">-- Select Animal Tag --</option>
<?php 
$allAnimals = $conn->query("SELECT tagId, name, animalType FROM Animals ORDER BY tagId"); 
while($animal = $allAnimals->fetch_assoc()): ?>
<option value="<?= htmlspecialchars($animal['tagId']) ?>"><?= htmlspecialchars($animal['tagId']) ?> - <?= htmlspecialchars($animal['name']) ?> (<?= $animal['animalType'] ?>)</option>
<?php endwhile; ?>
</datalist>
<small style="color:#666;"> </small><br><br>

<label>Health Status:</label>
<select name="type" required>
<option value="Healthy" <?= (isset($editHealth['type']) && $editHealth['type']=="Healthy")?"selected":"" ?>>✅ Healthy</option>
<option value="Sick" <?= (isset($editHealth['type']) && $editHealth['type']=="Sick")?"selected":"" ?>>🤒 Sick</option>
<option value="Pregnant" <?= (isset($editHealth['type']) && $editHealth['type']=="Pregnant")?"selected":"" ?>>🤰 Pregnant</option>
<option value="Treatment" <?= (isset($editHealth['type']) && $editHealth['type']=="Treatment")?"selected":"" ?>>💊 Treatment</option>
</select>

<input type="date" name="start" value="<?= htmlspecialchars($editHealth['startDate']??'') ?>" placeholder="Start Date">
<input type="date" name="end" value="<?= htmlspecialchars($editHealth['endDate']??'') ?>" placeholder="End Date">
<input type="date" name="next" value="<?= htmlspecialchars($editHealth['nextEventDate']??'') ?>" placeholder="Next Event Date">
<input name="notes" value="<?= htmlspecialchars($editHealth['notes']??'') ?>" placeholder="Notes / Observations">
<input name="vet" value="<?= htmlspecialchars($editHealth['vetName']??'') ?>" placeholder="Vet Name">
<input name="contact" value="<?= htmlspecialchars($editHealth['vetContact']??'') ?>" placeholder="Vet Contact">

<button type="submit" name="<?= $editHealth?'update_health':'save_health' ?>" style="background:#27ae60;color:white;border:none;padding:10px;cursor:pointer;border-radius:5px">
    <?= $editHealth?'Update Health Record':'Save Health Record' ?>
</button>
<?php if($editHealth): ?>
<a href="?page=records" style="display:inline-block;margin-left:10px;padding:10px;background:#999;color:white;text-decoration:none;border-radius:5px">Cancel Edit</a>
<?php endif; ?>
</form>

<h3> Existing Health Records</h3>
<table>
<tr><th>ID</th><th>Tag</th><th>Animal Name</th><th>Status</th><th>Start Date</th><th>End Date</th><th>Next Event</th><th>Vet</th><th>Actions</th></tr>
<?php 
$res = $conn->query("SELECT h.*, a.name as animal_name FROM health_records h LEFT JOIN Animals a ON h.tagId = a.tagId ORDER BY h.id DESC"); 
while($r=$res->fetch_assoc()): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><strong><?= htmlspecialchars($r['tagId']) ?></strong></td>
<td><?= htmlspecialchars($r['animal_name'] ?? 'Tag not found in Animals') ?></td>
<td>
    <?php 
    $statusIcon = '';
    if($r['type'] == 'Healthy') $statusIcon = '';
    elseif($r['type'] == 'Sick') $statusIcon = '';
    elseif($r['type'] == 'Pregnant') $statusIcon = '';
    else $statusIcon = '';
    echo $statusIcon.' '.htmlspecialchars($r['type']);
    ?>
</td>
<td><?= htmlspecialchars($r['startDate']) ?></td>
<td><?= htmlspecialchars($r['endDate']) ?></td>
<td><?= htmlspecialchars($r['nextEventDate']) ?></td>
<td><?= htmlspecialchars($r['vetName']) ?></td>
<td>
<a href="?page=records&edit_health=<?= $r['id'] ?>" style="color:#27ae60"> Edit</a> | 
<a href="?page=records&delete_health=<?= $r['id'] ?>" onclick="return confirm('Are you sure you want to delete health record ID <?= $r['id'] ?>?')" style="color:#e74c3c">🗑️ Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>

<?php if($res->num_rows == 0): ?>
<p style="background:#f8d7da;padding:10px;border-radius:5px">No health records found. Add an existing tag ID to create health records.</p>
<?php endif; ?>

<a href="?page=home" class="back-btn">← Back to Dashboard</a>
<?php endif; ?>

</div>
</body>
</html>