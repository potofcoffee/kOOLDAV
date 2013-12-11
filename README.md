# What is kOOLDAV

kOOLDAV is aiming to be a drop-in CardDAV server for kOOL. It allows users to synchronize their devices and addressbook with an existing kOOL install. Currently, it is read-only.

### Feature list:

* Supports browsing CardDAV addressbook while honoring user rights in kOOL.
* Based on popular [SabreDAV server](http://code.google.com/p/sabredav)

### What could become of this ...

* Two-way sync could be implemented with a reasonable amount of work
* Group support could be implemented, either as CardDAV categories or with an entry for each group
* CalDAV could eventually be implemented as well

### Current state

* At the moment, kOOLDAV is read-only, i.e. it only supports reading data out of kOOL into another database/device. 
* This is still quite a hack.

