<?php
// Prem Curry House - generate 2,500 SYNTHETIC local test orders for analytics.
// NOT genuine historical sales. Run only on a backed-up local development database.
if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin' || !isset($_SESSION['user_id'])) { http_response_code(403); exit('Administrator login required.'); }
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(403); exit('Localhost only.'); }
require_once __DIR__.'/../config/database.php';
$_SESSION['pch_seed_2020_2026'] ??= bin2hex(random_bytes(24));
$yearOrders = [2020=>320,2021=>410,2022=>290,2023=>450,2024=>380,2025=>360,2026=>290];
$message=''; $success=false;
function seedEsc($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function seedIsDrink($m) {
    return (bool)preg_match('/drink|beverage|coffee|tea|juice|kopi|teh|milo|nescafe|limau|sirap|horlicks|lassi|bru|latte|water|soda/i',
        ($m['category_name'] ?? '').' '.($m['item_name'] ?? ''));
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['pch_seed_2020_2026'], (string)($_POST['token'] ?? ''))) { http_response_code(403); exit('Invalid form token.'); }
    try {
        $existing = (int)$pdo->query("SELECT COUNT(*) FROM pch_food_orders WHERE customer_name LIKE 'Training Customer %'")->fetchColumn();
        if ($existing) throw new RuntimeException("There are already $existing training orders. Nothing inserted; avoid duplicates.");
        $menu=$pdo->query("SELECT m.item_id,m.item_name,m.price,COALESCE(c.category_name,'') AS category_name FROM menu_items m LEFT JOIN categories c ON c.category_id=m.category_id WHERE m.is_available=1 AND m.price > 0 ORDER BY m.item_id") ->fetchAll(PDO::FETCH_ASSOC);
        $foods=[]; $drinks=[];
        foreach($menu as $item) { if(seedIsDrink($item)) $drinks[]=$item; else $foods[]=$item; }
        if(count($foods)<5 || count($drinks)<2) throw new RuntimeException('Require at least 5 available foods and 2 beverages. Check menu classifications.');
        $pdo->beginTransaction();
        $order=$pdo->prepare("INSERT INTO pch_food_orders (user_id,customer_name,phone,fulfilment,address,payment_method,online_choice,payment_status,order_status,subtotal,tax_rate,tax_amount,delivery_fee,total,created_at) VALUES (NULL,?,'0000000000','pickup',NULL,?,?,?, ?,?,0,0,0,?,?)");
        $line=$pdo->prepare("INSERT INTO pch_food_order_items (order_id,menu_item_id,item_name,quantity,unit_price,line_total,options_json,remark) VALUES (?,?,?,?,?,?,'{}','')");
        $hasReason=(bool)$pdo->query("SHOW COLUMNS FROM pch_food_orders LIKE 'rejection_reason'")->fetch(PDO::FETCH_ASSOC);
        $reject=$hasReason?$pdo->prepare("UPDATE pch_food_orders SET rejection_reason='Food item unavailable' WHERE order_id=?"):null;
        mt_srand(20261117);
        $sequence=0;
        foreach($yearOrders as $year=>$count) {
            $from=strtotime("$year-01-01 09:00:00");
            $to=strtotime($year===2026?'2026-11-17 10:00:00':"$year-12-31 21:00:00");
            for($i=0;$i<$count;$i++) {
                $sequence++;
                // Vary monthly frequency: weekends slightly more likely, natural uneven annual pattern.
                $timestamp=mt_rand($from,$to);
                if(mt_rand(1,100)<=23) {
                    $try=$timestamp + (6-(int)date('w',$timestamp))*86400;
                    if($try<=$to && $try>=$from) $timestamp=$try;
                }
                $date=date('Y-m-d H:i:s',$timestamp);
                $basket=[];
                $foodQty=mt_rand(1,3); $drinkQty=mt_rand(0,2);
                for($j=0;$j<$foodQty;$j++) {
                    $idx=mt_rand(0,99)<55 ? mt_rand(0,min(9,count($foods)-1)) : mt_rand(0,count($foods)-1);
                    $basket[]=['item'=>$foods[$idx],'qty'=>mt_rand(1,3)];
                }
                for($j=0;$j<$drinkQty;$j++) {
                    $idx=mt_rand(0,99)<55 ? mt_rand(0,min(5,count($drinks)-1)) : mt_rand(0,count($drinks)-1);
                    $basket[]=['item'=>$drinks[$idx],'qty'=>mt_rand(1,2)];
                }
                $subtotal=0;
                foreach($basket as $r) $subtotal+=round((float)$r['item']['price']*$r['qty'],2);
                $subtotal=round($subtotal,2);
                $n=mt_rand(1,100);
                $status=$n<=68?'Completed':($n<=77?'Pending':($n<=85?'Confirmed':($n<=92?'Preparing':($n<=97?'Ready':'Rejected'))));
                // Some orders used counter, some used online choices. Test records do not report fake paid online payments.
                $online=mt_rand(1,100)<=35;
                $paymentMethod=$online?'online_demo':'counter';
                $choice=$online?['tng','fpx','card'][mt_rand(0,2)]:null;
                $paymentStatus='unpaid';
                $order->execute([sprintf('Training Customer %05d',$sequence),$paymentMethod,$choice,$paymentStatus,$status,$subtotal,$subtotal,$date]);
                $orderId=(int)$pdo->lastInsertId();
                if($status==='Rejected' && $reject) $reject->execute([$orderId]);
                foreach($basket as $r) {
                    $m=$r['item']; $qty=(int)$r['qty']; $price=(float)$m['price'];
                    $line->execute([$orderId,(int)$m['item_id'],$m['item_name'],$qty,$price,round($price*$qty,2)]);
                }
            }
        }
        $pdo->commit(); $success=true;
        $message='Created '.number_format($sequence).' synthetic development orders across 2020–2026.';
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); $message='No new orders added: '.$e->getMessage(); }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Food Order Data Setup</title><style>body{font:15px Arial,sans-serif;background:#f8f7f1;color:#263727;padding:35px 15px}.wrap{max-width:640px;background:#fff;margin:auto;padding:28px;border:1px solid #e0e8da;border-radius:16px}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #e9ede4;text-align:left}.btn{background:#527b35;color:white;border:0;padding:12px 18px;border-radius:8px;font-weight:bold;cursor:pointer}.notice{background:#f0f5eb;padding:14px;border-radius:9px}a{color:#527b35}</style></head><body><main class="wrap"><h1>Food Order Data Setup</h1><p>Generate 2,500 synthetic development orders with genuine existing menu item names and current menu prices. Historical dates and transactions are fictional. Back up the database first.</p><table><tr><th>Year</th><th>Orders</th></tr><?php foreach($yearOrders as $y=>$n): ?><tr><td><?= $y ?></td><td><?= $n ?></td></tr><?php endforeach; ?><tr><th>Total</th><th><?= number_format(array_sum($yearOrders)) ?></th></tr></table><?php if($message):?><p class="notice"><?= seedEsc($message) ?></p><?php endif; ?><?php if(!$success):?><form method="post"><input type="hidden" name="token" value="<?= seedEsc($_SESSION['pch_seed_2020_2026']) ?>"><p><button type="submit" class="btn">Create Food Order Dataset</button></p></form><?php endif; ?><p><a href="food_analytics.php?view=all">Open Food Analytics</a></p><p><small>Local project testing only. Keep training transactions distinguishable from genuine orders. Delete this installer after use.</small></p></main></body></html>
