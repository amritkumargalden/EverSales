create database if not exists eversales;
use eversales;

create table users(
    id int auto_increment primary key,
    full_name varchar(255) not null,
    email varchar(255) not null unique, 
    password varchar(255) not null,
    role enum('admin', 'customer', 'seller') not null,
    phone_number varchar(20),
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp
);

create table products(
    product_id int auto_increment primary key,
    name varchar(255) not null, 
    description text,
    price decimal(10,2) not null,
    stock int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp
);

create table orders(
    order_id int auto_increment primary key,
    user_id int not null,
    total_amount decimal(10,2) not null,
    status enum('pending', 'completed', 'cancelled') default 'pending',
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (user_id) references users(id)
);

create table reviews(
    review_id int auto_increment primary key,
    product_id int not null,
    user_id int not null,
    rating int not null,
    comment text,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (product_id) references products(product_id),
    foreign key (user_id) references users(id)
);

create table order_items(
    order_item_id int auto_increment primary key,
    order_id int not null,
    product_id int not null,
    quantity int not null,
    price decimal(10,2) not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (order_id) references orders(order_id),
    foreign key (product_id) references products(product_id)
);

create table payments(
    payment_id int auto_increment primary key,
    order_id int not null,
    amount decimal(10,2) not null,
    payment_method enum('credit_card', 'paypal', 'bank_transfer') not null,
    status enum('pending', 'completed', 'failed') default 'pending',
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (order_id) references orders(order_id)
);