#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pour extraire les données de WordPress/WooCommerce vers des CSV compatibles Thelia
"""

import re
import csv
import json
import os
from collections import defaultdict

class WordPressToTheliaConverter:
    def __init__(self, sql_file_path):
        self.sql_file_path = sql_file_path
        self.posts_data = []
        self.terms_data = []
        self.term_taxonomy_data = []
        self.postmeta_data = []
        self.term_relationships_data = []
        
        # Créer le répertoire de sortie
        self.output_dir = 'setup/csvfromsql'
        os.makedirs(self.output_dir, exist_ok=True)
        
    def parse_sql_insert(self, table_name):
        """Extrait les données d'INSERT SQL pour une table spécifique"""
        try:
            with open(self.sql_file_path, 'r', encoding='utf-8', errors='ignore') as file:
                content = file.read()
                
            # Find all INSERT statements for the table by looking for pattern more carefully
            # We'll look for INSERT INTO `table_name` ... VALUES and then find the ending );
            insert_pattern = rf"INSERT INTO `{table_name}`.*?VALUES\s*"
            insert_matches = list(re.finditer(insert_pattern, content, re.IGNORECASE))
            
            print(f"DEBUG: Found {len(insert_matches)} INSERT statements for table {table_name}")
            
            rows = []
            for i, match in enumerate(insert_matches):
                start_pos = match.end()
                
                # Find the end of this INSERT statement (look for ); at the end)
                remaining_content = content[start_pos:]
                
                # Look for the closing ); pattern that ends the INSERT
                end_match = re.search(r'\);', remaining_content)
                if end_match:
                    end_pos = start_pos + end_match.end()
                    insert_content = content[start_pos:end_pos]
                    
                    # Debug: Show we found an INSERT
                    # print(f"DEBUG: INSERT {i+1} processed, content length: {len(insert_content)}")
                    # print(f"DEBUG: First 100 chars of content: {insert_content[:100]}")
                    
                    # The content is already the VALUES part, so use it directly
                    values_str = insert_content
                    
                    # Replace newlines with spaces to make parsing easier
                    values_str = values_str.replace('\n', ' ').replace('\r', ' ')
                    
                    # print(f"DEBUG: VALUES string length: {len(values_str)}")
                    
                    # Use simple regex to split value groups
                    value_groups = re.findall(r'\([^)]*\)', values_str)
                    
                    # Debug: Show how many value groups we found
                    # if len(value_groups) > 0:
                    #     print(f"DEBUG: INSERT {i+1} found {len(value_groups)} value groups")
                    # else:
                    #     print(f"DEBUG: INSERT {i+1} found 0 value groups, values_str length: {len(values_str)}")
                    #     print(f"DEBUG: First 100 chars: {values_str[:100]}")
                    
                    # Parse each value group
                    for vg in value_groups:
                        row = self.parse_insert_values(vg)
                        if row:
                            rows.append(row)
                else:
                    print(f"Warning: Could not find end of INSERT statement {i+1} for {table_name}")
                            
            return rows
        except Exception as e:
            print(f"Erreur parsing table {table_name}: {e}")
            return []
    
    def parse_insert_values(self, values_str):
        """Parse une ligne de valeurs INSERT"""
        try:
            # Enlever les parenthèses extérieures
            content = values_str.strip()[1:-1]  # Remove ( and )
            
            # Handle empty content
            if not content:
                return []
            
            # Split by comma but respect quotes
            values = []
            current = ""
            in_quote = False
            quote_char = None
            
            for char in content:
                if not in_quote and char in ('"', "'"):
                    in_quote = True
                    quote_char = char
                elif in_quote and char == quote_char:
                    # Check if it's escaped
                    if len(current) > 0 and current[-1] == '\\':
                        current += char  # Keep the escaped quote
                    else:
                        in_quote = False
                        quote_char = None
                        current += char  # Close quote
                elif not in_quote and char == ',':
                    values.append(current.strip())
                    current = ""
                else:
                    current += char
            
            if current:
                values.append(current.strip())
            
            # Clean up each value
            parsed_values = []
            for value in values:
                # Remove surrounding quotes if present
                if value.startswith("'") and value.endswith("'"):
                    value = value[1:-1]
                elif value.startswith('"') and value.endswith('"'):
                    value = value[1:-1]
                
                # Handle NULL values
                if value.upper() == 'NULL':
                    parsed_values.append(None)
                else:
                    # Unescape common SQL escape sequences
                    unescaped = value.replace("\\'", "'").replace("\\\\", "\\")
                    parsed_values.append(unescaped)
            
            return parsed_values
        except Exception as e:
            print(f"Erreur parsing values: {e}")
            return []
    
    def extract_all_data(self):
        """Extrait toutes les données des tables WordPress"""
        print("Extraction des données WordPress...")
        
        # Extraire les données de chaque table avec le préfixe correct
        self.posts_data = self.parse_sql_insert('wnew23p_posts')
        print(f"Posts: {len(self.posts_data)} enregistrements")
        
        self.terms_data = self.parse_sql_insert('wnew23p_terms')
        print(f"Terms: {len(self.terms_data)} enregistrements")
        
        self.term_taxonomy_data = self.parse_sql_insert('wnew23p_term_taxonomy')
        print(f"Term Taxonomy: {len(self.term_taxonomy_data)} enregistrements")
        
        self.postmeta_data = self.parse_sql_insert('wnew23p_postmeta')
        print(f"Postmeta: {len(self.postmeta_data)} enregistrements")
        
        self.term_relationships_data = self.parse_sql_insert('wnew23p_term_relationships')
        print(f"Term Relationships: {len(self.term_relationships_data)} enregistrements")
    
    def get_post_meta(self, post_id, meta_key):
        """Récupère une valeur meta pour un post"""
        for meta in self.postmeta_data:
            if len(meta) >= 4 and meta[1] == str(post_id) and meta[2] == meta_key:
                return meta[3]
        return ""
    
    def get_post_metas(self, post_id):
        """Récupère toutes les metas pour un post"""
        metas = {}
        for meta in self.postmeta_data:
            if len(meta) >= 4 and meta[1] == str(post_id):
                metas[meta[2]] = meta[3]
        return metas
    
    def get_term_name(self, term_id):
        """Récupère le nom d'un terme"""
        for term in self.terms_data:
            if len(term) >= 2 and term[0] == str(term_id):
                return term[1]
        return ""
    
    def get_category_path(self, term_id):
        """Construit le chemin complet d'une catégorie"""
        path_parts = []
        current_id = term_id
        
        # Chercher la catégorie parente
        for tt in self.term_taxonomy_data:
            if len(tt) >= 4 and tt[1] == str(current_id) and tt[3] == 'category':
                parent_id = tt[4] if len(tt) > 4 else "0"
                if parent_id != "0" and parent_id != current_id:
                    path_parts.append(self.get_term_name(current_id))
                    # Récursivement chercher le parent (simplifié)
                    parent_name = self.get_term_name(parent_id)
                    if parent_name:
                        path_parts.append(parent_name)
                else:
                    path_parts.append(self.get_term_name(current_id))
                break
        
        return " > ".join(reversed(path_parts))
    
    def generate_categories_csv(self):
        """Génère le CSV des catégories"""
        print("Génération du CSV des catégories...")
        
        categories = []
        processed_terms = set()
        
        # Traiter les taxonomies de type catégorie
        for tt in self.term_taxonomy_data:
            if len(tt) >= 4 and tt[3] == 'category':
                term_id = tt[1]
                if term_id not in processed_terms:
                    processed_terms.add(term_id)
                    
                    category_name = self.get_term_name(term_id)
                    category_path = self.get_category_path(term_id)
                    
                    # Récupérer la description
                    description = ""
                    for term in self.terms_data:
                        if len(term) >= 4 and term[0] == term_id:
                            description = term[3] if len(term) > 3 else ""
                            break
                    
                    categories.append({
                        'CATEGORIES FR': category_path or category_name,
                        'CATEGORIES UK': category_name,
                        'CHAPO FR': description[:200] + "..." if len(description) > 200 else description,
                        'CHAPO UK': description[:200] + "..." if len(description) > 200 else description,
                        'DESCRIPTIF FR': description,
                        'DESCRIPTIF UK': description,
                        'PHOTO': ""
                    })
        
        # Écrire le CSV
        with open(os.path.join(self.output_dir, 'categories.csv'), 'w', encoding='utf-8', newline='') as csvfile:
            fieldnames = ['CATEGORIES FR', 'CATEGORIES UK', 'CHAPO FR', 'CHAPO UK', 'DESCRIPTIF FR', 'DESCRIPTIF UK', 'PHOTO']
            writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(categories)
        
        print(f"Categories CSV généré: {len(categories)} catégories")
    
    def generate_products_csv(self):
        """Génère le CSV des produits"""
        print("Génération du CSV des produits...")
        
        products = []
        product_count = 0
        
        # Traiter les posts de type produit
        for post in self.posts_data:
            if len(post) >= 22:  # Nombre de colonnes dans la table posts
                post_id = post[0]  # ID
                post_type = post[20]  # post_type est à l'index 20
                
                # Clean up post_type by removing extra quotes
                if post_type:
                    post_type = post_type.strip("'\"")
                
                # Debug: afficher les types de posts trouvés
                if post_type:
                    print(f"Type de post trouvé: '{post_type}' (repr: {repr(post_type)})")
                
                if post_type == 'product':
                    product_count += 1
                    post_title = post[5] if len(post) > 5 else ""  # post_title est à l'index 5
                    post_content = post[4] if len(post) > 4 else ""  # post_content est à l'index 4
                    post_excerpt = post[6] if len(post) > 6 else ""  # post_excerpt est à l'index 6
                    
                    # Handle encoding issues in print
                    try:
                        print(f"Produit trouvé: {post_title} (ID: {post_id})")
                    except UnicodeEncodeError:
                        print(f"Produit trouvé: [titre avec caractères spéciaux] (ID: {post_id})")
                    
                    # Récupérer les metas du produit
                    metas = self.get_post_metas(post_id)
                    
                    # Récupérer le prix
                    price = metas.get('_price', '0')
                    regular_price = metas.get('_regular_price', price)
                    sale_price = metas.get('_sale_price', '')
                    
                    # Récupérer la référence SKU
                    sku = metas.get('_sku', f'WP_{post_id}')
                    
                    # Récupérer les catégories
                    categories = []
                    for rel in self.term_relationships_data:
                        if len(rel) >= 3 and rel[0] == post_id:
                            term_taxonomy_id = rel[1]
                            for tt in self.term_taxonomy_data:
                                if len(tt) >= 4 and tt[0] == term_taxonomy_id and tt[3] == 'category':
                                    category_name = self.get_term_name(tt[1])
                                    if category_name:
                                        categories.append(category_name)
                    
                    category_path = " > ".join(categories) if categories else "NON CLASSÉ"
                    
                    # Récupérer la marque si disponible (comme attribut)
                    brand = ""
                    # Chercher dans les attributs WooCommerce
                    for meta_key, meta_value in metas.items():
                        if 'attribute' in meta_key.lower() and ('brand' in meta_key.lower() or 'marque' in meta_key.lower()):
                            brand = meta_value
                            break
                    
                    # Récupérer l'image
                    image_url = ""
                    thumbnail_id = metas.get('_thumbnail_id', '')
                    if thumbnail_id:
                        # Chercher l'URL de l'image dans les posts
                        for img_post in self.posts_data:
                            if len(img_post) >= 22 and img_post[0] == thumbnail_id and img_post[20] == 'attachment':
                                img_metas = self.get_post_metas(thumbnail_id)
                                image_url = img_metas.get('_wp_attached_file', '')
                                break
                    
                    product_data = {
                        'REF': sku,
                        'TITRE UK': post_title,
                        'CHAPO UK': post_excerpt[:200] + "..." if len(post_excerpt) > 200 else post_excerpt,
                        'CHAPO FR': post_excerpt[:200] + "..." if len(post_excerpt) > 200 else post_excerpt,
                        'DESCRIPTIF UK': post_content,
                        'DESCRIPTIF FR': post_content,
                        'POSTSCRIPTUM UK': '',
                        'POSTSCRIPTUM FR': '',
                        'PRIX': regular_price if regular_price else price,
                        'PRIX2': sale_price if sale_price else '',
                        'PHOTO': image_url.split('/')[-1] if image_url else '',
                        'BRAND': brand,
                        'COULEUR UK': '',
                        'MATERIAL UK': '',
                        'CONTENT UK': '',
                        'CATEGORIE': category_path
                    }
                    
                    products.append(product_data)
        
        print(f"Nombre total de produits trouvés: {product_count}")
        
        # Écrire le CSV
        with open(os.path.join(self.output_dir, 'products.csv'), 'w', encoding='utf-8', newline='') as csvfile:
            fieldnames = ['REF', 'TITRE UK', 'CHAPO UK', 'CHAPO FR', 'DESCRIPTIF UK', 'DESCRIPTIF FR', 
                         'POSTSCRIPTUM UK', 'POSTSCRIPTUM FR', 'PRIX', 'PRIX2', 'PHOTO', 'BRAND', 
                         'COULEUR UK', 'MATERIAL UK', 'CONTENT UK', 'CATEGORIE']
            writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(products)
        
        print(f"Products CSV généré: {len(products)} produits")
    
    def generate_brands_csv(self):
        """Génère le CSV des marques"""
        print("Génération du CSV des marques...")
        
        # Extraire les marques des attributs de produits
        brands = set()
        for post in self.posts_data:
            if len(post) >= 13 and post[12] == 'product':
                post_id = post[0]
                metas = self.get_post_metas(post_id)
                
                for meta_key, meta_value in metas.items():
                    if 'attribute' in meta_key.lower() and ('brand' in meta_key.lower() or 'marque' in meta_key.lower()):
                        if meta_value and meta_value != '':
                            brands.add(meta_value)
        
        # Créer le CSV des marques
        brands_data = [{'brand': brand} for brand in sorted(brands)]
        
        with open(os.path.join(self.output_dir, 'brands.csv'), 'w', encoding='utf-8', newline='') as csvfile:
            fieldnames = ['brand']
            writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(brands_data)
        
        print(f"Brands CSV généré: {len(brands_data)} marques")
    
    def generate_colors_csv(self):
        """Génère le CSV des couleurs"""
        print("Génération du CSV des couleurs...")
        
        # Extraire les couleurs des attributs de produits
        colors = set()
        for post in self.posts_data:
            if len(post) >= 13 and post[12] == 'product':
                post_id = post[0]
                metas = self.get_post_metas(post_id)
                
                for meta_key, meta_value in metas.items():
                    if 'attribute' in meta_key.lower() and ('color' in meta_key.lower() or 'couleur' in meta_key.lower()):
                        if meta_value and meta_value != '':
                            colors.add(meta_value)
        
        # Créer le CSV des couleurs
        colors_data = [{'color': color} for color in sorted(colors)]
        
        with open(os.path.join(self.output_dir, 'colors.csv'), 'w', encoding='utf-8', newline='') as csvfile:
            fieldnames = ['color']
            writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(colors_data)
        
        print(f"Colors CSV généré: {len(colors_data)} couleurs")
    
    def generate_materials_csv(self):
        """Génère le CSV des matériaux"""
        print("Génération du CSV des matériaux...")
        
        # Extraire les matériaux des attributs de produits
        materials = set()
        for post in self.posts_data:
            if len(post) >= 13 and post[12] == 'product':
                post_id = post[0]
                metas = self.get_post_metas(post_id)
                
                for meta_key, meta_value in metas.items():
                    if 'attribute' in meta_key.lower() and ('material' in meta_key.lower() or 'matiere' in meta_key.lower() or 'matériau' in meta_key.lower()):
                        if meta_value and meta_value != '':
                            materials.add(meta_value)
        
        # Créer le CSV des matériaux
        materials_data = [{'material': material} for material in sorted(materials)]
        
        with open(os.path.join(self.output_dir, 'materials.csv'), 'w', encoding='utf-8', newline='') as csvfile:
            fieldnames = ['material']
            writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(materials_data)
        
        print(f"Materials CSV généré: {len(materials_data)} matériaux")
    
    def convert_all(self):
        """Lance la conversion complète"""
        print("Début de la conversion WordPress vers Thelia...")
        
        # Extraire toutes les données
        self.extract_all_data()
        
        # Générer tous les fichiers CSV
        self.generate_categories_csv()
        self.generate_products_csv()
        self.generate_brands_csv()
        self.generate_colors_csv()
        self.generate_materials_csv()
        
        print(f"Conversion terminée! Fichiers CSV générés dans {self.output_dir}/")

if __name__ == "__main__":
    converter = WordPressToTheliaConverter("setup/uvpxgnzzfh.sql")
    converter.convert_all()
