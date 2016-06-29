
# Update search dates

```
host/jid/fr/jobs/gridDate.xml/from=2016-06-20%2010:33/to=2016-06-28%2010:33
```

# Get a list of every job between search dates

```
host/jid/fr/jobs/grid.xml/?bool=false?time=56&history=0&chained=0&only_warning=0&dhxr1467018236654=1
```

- bool : true or false, if you want to view all jobs or just the last instance of every job

To activate the mode to view chained job

```
host/jid/fr/jobs/gridChain.xml/bool=false
```
To activate the mode to view all jobs or just the last instance of every job


```
host/jid/fr/jobs/gridAll.xml/bool=true
```

# Information about a job

```
host/jid/fr/order/form.xml?id=18934006
```

- id : Job's id in the database

# Information job's order

```
host/jid/fr/orders/order_info/?id=18934006&type=order
```

# Job's History

```
host/jid/fr/order/history.xml?id=18934006
```
# Job's log

```
host/jid/fr/order/log.xml?id=18934006&dhxr1467019313443=1
```


