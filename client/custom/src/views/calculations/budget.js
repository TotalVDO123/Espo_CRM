define('custom:views/calculations/budget', ['view'], function (View) {
    return View.extend({
        template: 'custom:calculations/budget',

        setup: function () {
            this.filterValue = 'today';
            this.dateValue1 = new Date().toLocaleDateString().split('.').reverse().join('-');
            this.dateValue2 = this.dateValue1;
            this.expenses = [];
            this.sum = 0;
            this.income = { 
                list: [],
                total: { 
                    expenses: 0, 
                    profit: 0,
                    income: 0
                }
            }

            this.fetchIncome(this.dateValue1, this.dateValue1);

            this.initHandlers();
        },

        initHandlers: function() {
            this.addHandler('click', '#filterToday', 'handleFilterToday');
            this.addHandler('click', '#filterDate', 'handleFilterDate');
            this.addHandler('click', '#filterBetween', 'handleFilterDate');
            this.addHandler('change', '#dateDate', 'handleChangeDate');
            this.addHandler('change', '#dateBetween1', 'handleDateBetween1');
            this.addHandler('change', '#dateBetween2', 'handleDateBetween2');
            this.addHandler('click', '#findBetween', 'handleFindBetween');
        },

        handleFilterToday: function(e) {
            this.filterValue = e.target.value;
            this.dateValue1 = new Date().toLocaleDateString().split('.').reverse().join('-');
            this.fetchIncome(this.dateValue1, this.dateValue1);
        },

        handleFilterDate: function(e) {
            this.filterValue = e.target.value;
            this.reRender();
        },
        

        handleChangeDate: function(e) {
            this.dateValue1 = e.target.value;
            console.log(this.dateValue1);
            this.fetchIncome(this.dateValue1);
            //this.fetchExpenses(this.filterValue, this.dateValue1);
        },

        fetchIncome: async function(dateFrom, dateTo) {
            try {
                let income = await fetch(`api/v1/Budget/income/${dateFrom}/${dateTo}`);
                income = await income.json();

                console.log(income);
                this.income = income;
                this.reRender();
            } catch (error) {
                console.error(error);
            }
        },

        handleDateBetween1: function(e) {
            this.dateValue1 = e.target.value;
        },

        handleDateBetween2: function(e) {
            this.dateValue2 = e.target.value;
        },

        handleFindBetween: function(e) {
            this.fetchIncome(this.dateValue1, this.dateValue2);
        },

        fetchExpenses: function(filterValue, date1, date2) {
            //switch instead object, bc with object to complicated spaghetti 
            switch (filterValue) {
                case 'today':
                    this.fetchExpensesByDate(date1)
                        .then(expenses => this.setExpenses(expenses))
                        .catch(error => console.error(error));
                    break;
                case 'date':
                    this.fetchExpensesByDate(date1)
                    .then(expenses => this.setExpenses(expenses))
                    .catch(error => console.error(error));
                    break;
                case 'between':
                    this.fetchExpensesBetweenDate(date1, date2)
                    .then(expenses => this.setExpenses(expenses))
                    .catch(error => console.error(error));
                    break;
            }
        },
        
        fetchExpensesByDate: function(date) {
            return this.getCollectionFactory().create('Expenses')
                .then(collection => {
                    collection.maxSize = 10000;
                    collection.where = [{
                        "type": "equals",
                        "attribute": "date",
                        "value": date,
                    }];
                    return collection.fetch();
                })
        },

        fetchExpensesBetweenDate: function(date1, date2) {
            return this.getCollectionFactory().create('Expenses')
                .then(collection => {
                    collection.maxSize = 10000;
                    collection.where = [{
                        "type": "between",
                        "attribute": "date",
                        "value": [date1, date2],
                    }];
                    return collection.fetch();
                })
        },

        afterRender: function () {
           //hightlight button with blue
           this.$el.find(`button[value=${this.filterValue}`)[0].classList.add('btn-primary');
        },

        setExpenses: function(expenses) {
            this.expenses = expenses;
            this.sum = this.calculateExpansesSum(expenses.list);
            this.reRender();
        },

        calculateExpansesSum: function(expenses) {
            let sum = 0;
            expenses.forEach(exp => sum += exp.cost);
            return sum;
        },

        data: function () {
            return {
                filterValue: this.filterValue,
                dateValue1: this.dateValue1,
                dateValue2: this.dateValue2,
                sum: this.sum.toLocaleString('en'),
                expenses: this.expenses.list,
                expensesTotal: this.expenses.total,

                profitTotalSum: this.income.total.profit,
                expensesTotalSum: this.income.total.expenses,
                incomeTotalSum: this.income.total.income,

                incomeList: this.income.list
            };
        },
    });
});