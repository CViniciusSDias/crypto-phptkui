if {![package vsatisfies [package provide Tcl] 8.3]} {return}
package ifneeded tls 1.8.0 "source \[file join [list $dir] tls.tcl\] ; tls::initlib [list $dir] tls180.dll"
