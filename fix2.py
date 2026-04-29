#!/usr/bin/env python3
f = open('routes/web.php', 'r').read()

# Find and show what's there
import re
matches = re.findall(r"'store'\s+=>\s+'([^']+)'", f)
print("Found store values:", matches)

# Replace using regex
f = re.sub(r"('store'\s+=>\s+')[^']+(')", r"\g<1>admin.notifications.store\2", f, count=1)
f = re.sub(r"('show'\s+=>\s+')[^']+(')", r"\g<1>admin.notifications.show\2", f, count=1)

open('routes/web.php', 'w').write(f)
print('Done!')
