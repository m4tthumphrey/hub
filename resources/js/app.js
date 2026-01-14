import './bootstrap';

const fields = document.getElementsByClassName('amount')

for (const field of fields) {
    field.addEventListener('change', (e) => {
        axios.post('/wealth/update', {
            id: e.target.dataset.id,
            name: e.target.dataset.name,
            value: e.target.value,
        })
    })
}
