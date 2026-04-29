#!/usr/bin/env python3
f = open('routes/web.php', 'r').read()

notif_store = 'admin.notifications.store'
notif_show = 'admin.notifications.show'

old_admin = """    Route::resource('notifications', NotificationController::class)->names([
        'index'   => 'admin.notifications.index',
        'create'  => 'admin.notifications.create',
        'store'   => '""" + notif_store + """',
        'show'    => '""" + notif_show + """',
        'edit'    => 'admin.notifications.edit',
        'update'  => 'admin.notifications.update',
        'destroy' => 'admin.notifications.destroy',
    ]);
    // \u2705 FIX: Admin can also dismiss notifications (for their own banner management)
    Route::post('notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('admin.notifications.dismiss');"""

old_accounting = "    Route::delete('/fee-settings/{feeSetting}', [FeeSettingsController::class, 'destroy'])->name('accounting.fee-settings.destroy');\n});"

new_accounting = """    Route::delete('/fee-settings/{feeSetting}', [FeeSettingsController::class, 'destroy'])->name('accounting.fee-settings.destroy');
    Route::resource('notifications', NotificationController::class)->names([
        'index'   => 'admin.notifications.index',
        'create'  => 'admin.notifications.create',
        'store'   => '""" + notif_store + """',
        'show'    => '""" + notif_show + """',
        'edit'    => 'admin.notifications.edit',
        'update'  => 'admin.notifications.update',
        'destroy' => 'admin.notifications.destroy',
    ]);
    Route::post('notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('admin.notifications.dismiss');
});"""

f = f.replace(old_admin, '')
f = f.replace(old_accounting, new_accounting)
open('routes/web.php', 'w').write(f)
print('Done!')
