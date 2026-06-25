<?php
$conn = mysqli_connect("localhost", "root", "", "parkingdb");
$msg = ""; 

// 1. ADD NEW VEHICLE (With Subquery Implementation)
if(isset($_POST['add_vehicle'])){
    $name = mysqli_real_escape_string($conn, $_POST['owner_name']);
    $plate = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $type = $_POST['v_type'];

    // [Subquery]: Check if plate exists using a nested SELECT
    $check = mysqli_query($conn, "SELECT PlateNumber FROM Vehicles WHERE PlateNumber = (SELECT '$plate')");
    
    if(mysqli_num_rows($check) == 0) {
        $q = mysqli_query($conn, "INSERT INTO Vehicles (OwnerName, PlateNumber, VehicleType) VALUES ('$name', '$plate', '$type')");
        if($q) $msg = "Vehicle Registered Successfully!";
    } else {
        $msg = "Error: Plate Number already exists!";
    }
}

// 2. ADD PARKING ENTRY (With Stored Procedure & Transaction)
if(isset($_POST['add_entry'])){
    $vid = $_POST['vid'];
    $sid = $_POST['sid'];
    $fee = $_POST['fee'];

    // [Transaction Start]: Ensuring data integrity
    mysqli_begin_transaction($conn);
    
    // [Stored Procedure]: Hum logic procedure se handle kar rahe hain
    $q = mysqli_query($conn, "CALL AddParkingRecord($vid, $sid, $fee)");
    
    if($q) {
        mysqli_commit($conn);
        $msg = "Parking Record Added Successfully!";
    } else {
        mysqli_rollback($conn);
        $msg = "Transaction Failed!";
    }
}

// 3. EXIT / CHECKOUT (Trigger will work here)
if(isset($_GET['exit'])){
    $rid = $_GET['exit'];
    $exit_time = date('Y-m-d H:i:s');
    
    // [Trigger Alert]: Backend trigger 'after_parking_exit' auto-updates the Slot status
    $q = mysqli_query($conn, "UPDATE ParkingRecords SET ExitTime='$exit_time' WHERE RecordID=$rid");
    
    if($q) $msg = "Vehicle Checked Out! (Slot Auto-Released by Trigger)";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Smart Parking Pro | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-custom { background: #1e293b; color: white; padding: 15px; border-bottom: 4px solid #38bdf8; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: 0.3s; }
        .card:hover { transform: translateY(-3px); }
        .btn-add { background: #38bdf8; color: white; border-radius: 8px; font-weight: 600; border: none; }
        .btn-add:hover { background: #0ea5e9; color: white; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; }
    </style>
</head>
<body>

<div class="navbar-custom d-flex justify-content-between align-items-center">
    <h4 class="m-0"><i class="fa-solid fa-square-p me-2 text-info"></i> SMART PARKING PRO</h4>
    <div class="small fw-light">Advanced DB Management System v2.0</div>
</div>

<div class="container mt-4">
    
    <?php if($msg != ""): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- STATS AREA (Aggregate Functions & Group By) -->
    <div class="row g-3 mb-4 text-center">
        <div class="col-md-3">
            <div class="card p-3 bg-white border-start border-primary border-5">
                <div class="text-muted small fw-bold">TOTAL REVENUE (SUM)</div>
                <h2 class="text-dark">Rs. <?php $r=mysqli_query($conn,"SELECT SUM(Fee) as s FROM ParkingRecords"); echo mysqli_fetch_assoc($r)['s'] ?? 0; ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 bg-white border-start border-success border-5">
                <div class="text-muted small fw-bold">FREE SLOTS (COUNT)</div>
                <h2 class="text-dark"><?php $r=mysqli_query($conn,"SELECT COUNT(*) as c FROM ParkingSlots WHERE Status='Available'"); echo mysqli_fetch_assoc($r)['c']; ?></h2>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3 bg-white border-start border-warning border-5">
                <div class="text-muted small fw-bold mb-2">VEHICLE DISTRIBUTION (GROUP BY)</div>
                <?php 
                //Group by having aggregate function 1 line 
                    $gb = mysqli_query($conn, "SELECT VehicleType, COUNT(*) as total FROM Vehicles GROUP BY VehicleType HAVING total > 0");
                    while($grow = mysqli_fetch_assoc($gb)) {
                        echo "<span class='badge bg-dark mx-1 p-2'>{$grow['VehicleType']}: {$grow['total']} Units</span>";
                    }
                ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-3 mb-4">
        <button class="btn btn-add px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#vehicleModal"><i class="fa fa-car-side me-2"></i>Step 1: Register</button>
        <button class="btn btn-dark px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#entryModal"><i class="fa fa-ticket me-2"></i>Step 2: Park</button>
    </div>

    <!-- MAIN DATA TABLE (Joins, View, Function, Order By) -->
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="m-0 text-secondary fw-bold">Live Traffic Log (SQL View Implementation)</h5>
        </div>
        <div class="table-responsive p-3">
            <table class="table align-middle table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Owner</th>
                        <th>Plate No.</th>
                        <th>Location</th>
                        <th>Entry Time</th>
                        <th>Taxed Fee (SQL Func)</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    //function getfeewithtax
                    // joins, view ,orderby bhi same line first mein hui 
                    // [View]: Fetched from vw_ParkingDetails (which uses JOINs)
                    // [Function]: GetFeeWithTax(Fee) adds GST in real-time
                    // [Order By]: Latest entries first
                    $res = mysqli_query($conn, "SELECT *, GetFeeWithTax(Fee) as TaxedFee FROM vw_ParkingDetails ORDER BY RecordID DESC");
                    while($row = mysqli_fetch_assoc($res)){
                        $badge = $row['ExitTime'] ? '<span class="status-badge bg-light text-muted border">Exited</span>' : '<span class="status-badge bg-success text-white">Parked</span>';
                        echo "<tr>
                            <td class='fw-bold text-capitalize'>{$row['OwnerName']}</td>
                            <td><span class='badge bg-dark px-3'>{$row['PlateNumber']}</span></td>
                            <td>Slot No. {$row['SlotNumber']}</td>
                            <td>".date('h:i A', strtotime($row['EntryTime']))."</td>
                            <td class='text-primary fw-bold'>Rs. ".number_format($row['TaxedFee'], 2)."</td>
                            <td>$badge</td>
                            <td>
                                ".($row['ExitTime'] ? '<i class="fa fa-check-double text-muted"></i>' : "<a href='?exit={$row['RecordID']}' class='btn btn-sm btn-outline-danger px-3'>Checkout</a>")."
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: ADD VEHICLE -->
<div class="modal fade" id="vehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-light"><h5><i class="fa fa-user-plus me-2"></i>Register New Owner</h5></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Owner Full Name</label><input type="text" name="owner_name" class="form-control" placeholder="e.g. Ali Ahmed" required></div>
                <div class="mb-3"><label class="form-label">Plate Number</label><input type="text" name="plate_number" class="form-control" placeholder="ABC-1234" required></div>
                <div class="mb-3"><label class="form-label">Vehicle Category</label><select name="v_type" class="form-select"><option>Car</option><option>Bike</option><option>Truck</option></select></div>
            </div>
            <div class="modal-footer border-0"><button type="submit" name="add_vehicle" class="btn btn-add w-100 py-2">Save Registration</button></div>
        </form>
    </div>
</div>

<!-- MODAL: PARK VEHICLE -->
<div class="modal fade" id="entryModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white"><h5><i class="fa fa-parking me-2"></i>Issue Parking Ticket</h5></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Vehicle Owner</label>
                    <select name="vid" class="form-select">
                        <?php $v = mysqli_query($conn, "SELECT * FROM Vehicles"); while($r=mysqli_fetch_assoc($v)) echo "<option value='{$r['VehicleID']}'>{$r['OwnerName']} ({$r['PlateNumber']})</option>"; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Available Parking Spot</label>
                    <select name="sid" class="form-select">
                        <?php $s = mysqli_query($conn, "SELECT * FROM ParkingSlots WHERE Status='Available'"); while($r=mysqli_fetch_assoc($s)) echo "<option value='{$r['SlotID']}'>Spot {$r['SlotNumber']}</option>"; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Standard Base Fee (Rs.)</label><input type="number" name="fee" class="form-control" value="150"></div>
            </div>
            <div class="modal-footer border-0"><button type="submit" name="add_entry" class="btn btn-dark w-100 py-2">Confirm Entry</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>