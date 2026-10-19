$src = 'C:\Users\EXAUCE\.cursor\projects\c-Users-EXAUCE-Documents-focus\assets\c__Users_EXAUCE_AppData_Roaming_Cursor_User_workspaceStorage_empty-window_images_IMG-20260812-WA0030-b0e1cdab-a04b-40b8-8d4e-14c12f263627.png'
$dest1 = 'C:\Users\EXAUCE\Documents\focus\assets\img\logo-congo-events.png'
$dest2 = 'C:\xamppss\htdocs\focus\assets\img\logo-congo-events.png'
New-Item -ItemType Directory -Force -Path (Split-Path $dest1) | Out-Null
New-Item -ItemType Directory -Force -Path (Split-Path $dest2) | Out-Null
Copy-Item -LiteralPath $src -Destination $dest1 -Force
Copy-Item -LiteralPath $src -Destination $dest2 -Force
$r1 = Get-Item -LiteralPath $dest1
$r2 = Get-Item -LiteralPath $dest2
"EXISTS=True SIZE=$($r1.Length) PATH=$($r1.FullName)"
"EXISTS=True SIZE=$($r2.Length) PATH=$($r2.FullName)"
