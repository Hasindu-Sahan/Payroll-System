# TODO
- [ ] Update `e_leaves.php` date inputs: set `min` to today for Start Date and End Date (employee leave application UI).
- [ ] Add server-side validation in `e_leaves.php` for `start_date` and `end_date` to ensure they are today or after.
- [ ] Add server-side validation that `end_date >= start_date`.
- [ ] Display a clear toast message on validation failure.
- [ ] Manually test the leave form scenarios (today, future, yesterday, end<start).

