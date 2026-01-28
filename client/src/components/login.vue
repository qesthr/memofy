<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const email = ref('')
const password = ref('')

const login = async () => {
  try {
    const res = await api.post('/login', {
      email: email.value,
      password: password.value
    })
    localStorage.setItem('token', res.data.token)
    console.log('Logged in', res.data.user)
  } catch (err) {
    alert('Login failed')
  }
}
</script>
