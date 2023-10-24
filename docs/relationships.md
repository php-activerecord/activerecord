# Relationships

## Introduction
   ActiveRecord allows you to define and work with relationships between database tables. These relationships mirror real-world associations, such as a User having many Posts or a Product belonging to many Categories.

##  Types of Relationships
There are three main types of relationships in ActiveRecord:

**One-to-One**: A record in one table is associated with one record in another table.

**One-to-Many**: A record in one table is associated with multiple records in another table.

**Many-to-Many**: Records in one table are associated with multiple records in another table, and vice versa.


## Defining Relationships
   To define relationships, you'll need to set up relationships between ActiveRecord models using static properties like `belongs_to`, `has_many`, and `has_and_belongs_to_many`.
