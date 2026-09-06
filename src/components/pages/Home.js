import React from 'react'
import Carousels from '../Layouts/Carousels'
import Footer from '../Layouts/Footer'
import Navbar from '../Layouts/Navbar'
import Blogheadlines from '../Layouts/Blogheadlines'
import Accessories from '../Layouts/Accessories'
import Laptops from '../Layouts/Laptops'
import Pc from '../Layouts/Pc'
import Searchbar from '../Layouts/Searchbar'


function Home() {
    return (
        <div>
        <Searchbar/>
        <Navbar/>
        <Carousels/>
        <Pc/>
        <Laptops/>
        <Accessories/>
        <Blogheadlines/>
        <Footer/>

        </div>
    )
}

export default Home
