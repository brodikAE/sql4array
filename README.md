# sql4array

This class can be used to query PHP arrays using an SQL dialect.

It can execute SQL SELECT queries on bi-dimensional array on which the first dimension is the row number and the second dimension the field names.

The class supports the WHERE clause to specify conditions using operators like =, >, <, LIKE, IN, etc..

The results are returned as arrays and can be sorted with the ORDER clause and restricted with the clause LIMIT.
