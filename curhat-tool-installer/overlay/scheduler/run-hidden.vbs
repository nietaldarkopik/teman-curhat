' Menjalankan run-scheduler.bat tanpa memunculkan jendela hitam.
Set sh = CreateObject("WScript.Shell")
dir = CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName)
sh.Run """" & dir & "\run-scheduler.bat""", 0, True
