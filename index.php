<?php
  
    include('kia.php');
    
    $email = '';      //your kia uvo email
    $password = '';   //your kia uvo password
    $pin = '';        //your 4 digits pin code
    
    $status = Kia::getStatus($email, $password, $pin);
    
?>
  
<h1>Batterie: <?php echo $status['battery_status']; ?>%</h1>
<h1">Autonomie: <?php echo $status['range']; ?> km</h1>

<?php if (!$status['battery_charge']): ?>
    <h1>Pas de charge en cours</h1>
<?php else: ?>
    <h1>Charge en cours</h1>
    <h1>Terminée dans <?php echo $status['charge_delay']; ?></h1>
<?php endif; ?>
