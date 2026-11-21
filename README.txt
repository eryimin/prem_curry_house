PREM CURRY HOUSE FOOD ANALYTICS UPDATE

1. Back up prem_curry_house_db in phpMyAdmin and back up original PHP files.
2. Replace C:\xampp\htdocs\prem_curry_house\admin\food_analytics.php with food_analytics.php.
3. Replace your existing shared admin sidebar file with sidebar.php.
   Locate it using VS Code Ctrl+Shift+F for "Reusable admin navigation".
   Existing dashboard code may use admin/includes/sidebar.php; do not create a second sidebar at a different location.
4. Put seed_food_orders.php in C:\xampp\htdocs\prem_curry_house\admin\seed_food_orders.php.
5. Log in as admin on local XAMPP and visit http://localhost/prem_curry_house/admin/seed_food_orders.php.
6. Run once to insert 2500 labelled synthetic orders, with yearly counts 320,410,290,450,380,360,290.
7. Delete seed_food_orders.php after use; never upload it to a live site.
8. Open http://localhost/prem_curry_house/admin/food_analytics.php?view=all.
9. Dashboard link points to admin/dashboard.php. If dashboard.php itself fails, copy its exact error message; these files cannot automatically repair an unrelated PHP error.

These are synthetic training orders, not historical restaurant sales. They are marked 'Training Customer' in the database.
